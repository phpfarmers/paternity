<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Imports\SampleImport;
use App\Models\Sample;
use App\Models\Family;
use App\Models\FamilySample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\FlareClient\Api;

class SampleService extends BaseService
{
    public function __construct()
    {
    }

    /**
     * 导入 Excel 文件并处理数据
     *
     * @param Request $request
     * @return void
     */
    public function import($request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx'
        ]);

        $file = $request->file('file');

        // 用excel读取Excel文件数据
        $data = Excel::toArray(new SampleImport, $file);
        throw_if(empty($data[0]), new \Exception('Excel文件数据为空'));
        // 取出第一个sheet的数据
        $sampleData = $data[0];

        // [0] => 序号
        // [1] => 分析批次
        // [2] => 样本名称
        // [3] => 所属家系
        // [4] => 称谓
        // [5] => 孕周
        // [6] => 分析流程

        // 删除第一行--表头
        if($sampleData[0][0] == '序号') {
            unset($sampleData[0]);
        }
        // 分组--按家系
        $sampleData = array_reduce($sampleData, function ($carry, $item) {
            $familyName = $item[3] ?? '';
            if (!isset($carry[$familyName])) {
                $carry[$familyName] = [];
            }
            $carry[$familyName][] = $item;
            return $carry;
        }, []);

        
        // mysql事务开始
        DB::beginTransaction();
        try {
            foreach ($sampleData as $familyName => $rows) {
                // 插入家系表
                $familyId = Family::insertGetId([
                    'name' => $familyName,
                ]);
                foreach ($rows as $row) {
                    // 样本类型-键值反转
                    $sampleTypeMap = Sample::SAMPLE_TYPE_MAP;
                    if (count($sampleTypeMap) !== count(array_unique($sampleTypeMap))) {
                        throw new \Exception('SAMPLE_TYPE_MAP 中存在重复值，无法进行键值反转');
                    }
                    $arrayFlip = array_flip($sampleTypeMap);
                    // 样本类型
                    $sampleType = $arrayFlip[$row[4]] ?? Sample::SAMPLE_TYPE_DEFAULT;
                    // 孕周
                    $pregnancyWeek = $row[5] ?? '';
                    // 分析流程：空或umi
                    $analysisProcess = $row[6] ?? '';
                    
                    throw_if($sampleType==Sample::SAMPLE_TYPE_DEFAULT, new \Exception('家系【'.$familyName.'】样本类型错误'));
                    // 插入样本表
                    $sampleId = Sample::insertGetId([
                        'batch_number'  => $row[1] ?? '',
                        'sample_type'   => $sampleType,
                        'sample_name'   => $row[2] ?? '',
                        'family_name'   => $familyName,
                        'pregnancy_week'=> $pregnancyWeek,
                        'analysis_process' => $analysisProcess,
                    ]);

                    // 插入家系样本关系表
                    FamilySample::insert([
                        'family_id' => $familyId,
                        'sample_id' => $sampleId
                    ]);
                }
            }
            // 提交事务
            DB::commit();
        } catch (\Exception $e) {
            // 回滚事务
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
        
        return true;
    }

    /**
     * 样本检测运行
     * 
     * @param Request $request
     * @return bool
     */
    public function checkRun(Request $request)
    {
        $sample = Sample::find($request->input('sample_id'));
        throw_unless($sample, new \Exception('样本信息不存在！'));
        // 验证样本检测结果-仅未检测状态可运行
        $checkResult = Sample::CHECK_RESULT_MAP_NAMES[$sample->check_result]??'';
        throw_unless($sample->check_result == Sample::CHECK_RESULT_UNKNOWN, new ApiException(1, $checkResult.'检测结果，不可运行！'));

        DB::beginTransaction();
        try {
            // 检测样本接口
            $result = $this->checkExec($sample->sample_name);
            if(!$result){
                throw new ApiException(1, '样本检测失败！');
            }
            // 检测成功-修改状态
            $sample->check_result = Sample::CHECK_RESULT_SUCCESS;
            $sample->off_machine_time = date('Y-m-d');
            $sample->r1_url = $result['r1_url'];
            $sample->r2_url = $result['r2_url'];
            if($sample->isDirty()){
                $sample->save();
            }
            // TODO:记录日志
            DB::commit();
            return true;
        }catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 样本检测重运行
     * 
     * @param Request $request
     * @return bool
     * @throws ApiException
     */
    public function checkRerun(Request $request)
    {
        $sample = Sample::find($request->input('sample_id'));
        throw_unless($sample, new \Exception('样本信息不存在！'));
        // 非失败结果-不可重运行
        if ($sample->check_result != Sample::CHECK_RESULT_FAIL) {
            throw new ApiException(1, '样本检测结果非失败，不可重运行！');
        }

        DB::beginTransaction();
        try {
            // 检测重运行接口
            $result = $this->checkExec($sample->sample_name);
            if(!$result){
                throw new ApiException(1, '样本检测失败！');
            }
            $sample->check_result = Sample::CHECK_RESULT_SUCCESS;
            $sample->off_machine_time = date('Y-m-d');
            $sample->r1_url = $result['r1_url'];
            $sample->r2_url = $result['r2_url'];
            if($sample->isDirty()){
                $sample->save();
            }
            // TODO:记录日志
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 样本检测执行
     * 
     * @param string $sampleName
     * @param string $searchPath
     * 
     * @return bool|array
     */

    public function checkExec(string $sampleName = '', string $searchPath = '/akdata/oss_data/')
    {
        // shell命令参数
        $searchPattern = escapeshellarg('*'.$sampleName.'*.gz'); // 搜索模式-样本名
        // $searchPattern = escapeshellarg('*Ignition.php'); // 测试
        $searchPath = escapeshellarg($searchPath); // 搜索路径
        $command = "find {$searchPath} -name {$searchPattern}";
        // 执行shell命令
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            return false;
        }
        $r1Url = ''; // 样本R1文件路径
        $r2Url = ''; // 样本R2文件路径
        foreach ($output as $file) {
            // 获取文件名
            $fileName = basename($file);
            // r1文件路径
            if (strpos($fileName, '1.fq.gz') !== false || strpos($fileName, '1.fastq.gz') !== false) {
                $r1Url = $file;
            }
            // r2文件路径
            if (strpos($fileName, '2.fq.gz') !== false || strpos($fileName, '2.fastq.gz') !== false) {
                $r2Url = $file;
            }
        }
        // 检测规则
        // 符合条件-更新检测结果状态为成功
        if(count($output) != 2 || empty($r1Url) || empty($r2Url)){
            return false;
        }
        return ['r1_url' => $r1Url, 'r2_url' => $r2Url];
    }

    /**
     * 样本分析运行
     * 
     * @param Request $request
     * @return bool
     */
    public function analysisRun(Request $request)
    {
        $sample = Sample::find($request->input('sample_id'));
        throw_unless($sample, new ApiException(1,'样本信息不存在！'));
        // 验证样本分析结果-仅未分析状态可运行
        throw_unless($sample->analysis_result == Sample::ANALYSIS_RESULT_UNKNOWN, new ApiException(1,'样本分析结果-仅未分析状态可运行！'));

        DB::beginTransaction();
        try {
            // 样本分析接口
            $result = $this->analysisExec($sample->sample_name, $sample->r1_url, $sample->r2_url, $sample->analysis_process);
            if(!$result){
                throw new ApiException(1, '样本分析失败！');
            }
            // 更新样本分析结果
            $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
            $sample->analysis_time = date('Y-m-d H:i:s');
            $sample->save();
            // TODO:记录日志
            DB::commit();
            return true;
        }catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 样本分析重运行
     * 
     * @param Request $request
     * @return bool
     * @throws ApiException
     */
    public function analysisRerun(Request $request)
    {
        $sample = Sample::find($request->input('sample_id'));
        throw_unless($sample, new ApiException(1,'样本信息不存在！'));
        // 非失败结果-不可重运行
        throw_if($sample->analysis_result != Sample::ANALYSIS_RESULT_FAIL, new ApiException(1,'样本分析结果非失败，不可重运行！'));

        DB::beginTransaction();
        try {
            // 样本分析重运行接口
            $result = $this->analysisExec($sample->sample_name, $sample->r1_url, $sample->r2_url, $sample->analysis_process);
            if(!$result){
                throw new ApiException(1, '样本分析失败！');
            }
            // 更新样本分析结果
            $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
            $sample->analysis_time = date('Y-m-d H:i:s');
            $sample->save();
            // TODO:记录日志
            DB::commit();
            return true;
        }catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 样本分析执行
     * 
     * @param string $sampleName
     * @param string $r1Url
     * @param string $r2Url
     * @param string $analysisProcess
     * @param string $outputDir
     * @return bool
     */
    public function analysisExec(string $sampleName = '', string $r1Url = '', string $r2Url = '', string $analysisProcess = '', string $outputDir = '')
    {
        // shell命令参数
        $sampleName = escapeshellarg($sampleName);
        $r1Url = escapeshellarg($r1Url);
        $r2Url = escapeshellarg($r2Url);
        $analysisProcess = escapeshellarg($analysisProcess);
        $outputDir = escapeshellarg($outputDir); // 输出路径
        $commandPl = config('data')['sample_analysis_run_command_pl'];
        $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url} -u {$analysisProcess} -o {$outputDir} ";
        // 执行shell命令
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            return false;
        }
        // foreach ($output as $file) {
        //     // 获取文件名
        //     $fileName = basename($file);
        // }
        // 符合条件-更新检测结果状态为成功
        if(count($output) < 1){
            return false;
        }
        return true;
    }
}