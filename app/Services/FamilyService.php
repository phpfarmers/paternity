<?php

namespace App\Services;

use App\Console\Tools\Office\Excel;
use App\Exceptions\ApiException;
use App\Jobs\ConcurrentFamilyAnalysisJob;
use App\Jobs\FamilyAnalysisRunJob;
use App\Models\Family;
use App\Models\Sample;
use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FamilyService extends BaseService
{
    public function __construct() {}

    /**
     * 获取首页初始数据
     *
     * @param Request $request
     * @return array
     */
    public function index()
    {
        return [
            'sample_type_map_names' => Sample::SAMPLE_TYPE_MAP_NAMES,
            'check_result_map_names' => Sample::CHECK_RESULT_MAP_NAMES,
            'analysis_result_map_names' => Sample::ANALYSIS_RESULT_MAP_NAMES,
            'report_result_map_names' => Family::REPORT_RESULT_MAP_NAMES
        ];
    }

    /**
     * 获取家庭表格数据
     *
     * @param Request $request
     * @return array
     */
    public function getTableData(Request $request)
    {
        $query = Family::query()->with('samples');
        if ($request->has('off_machine_time') && $request->input('off_machine_time') != '') {
            $offMachineTime = explode(' - ', $request->input('off_machine_time'));
            // 查询样本表的离线时间，关联到家系表
            $query->whereHas('samples', function ($q) use ($offMachineTime) {
                $q->whereBetween('off_machine_time', [$offMachineTime[0], $offMachineTime[1]]);
            });
        }

        if ($request->has('analysis_time') && $request->input('analysis_time') != '') {
            $analysisTime = explode(' - ', $request->input('analysis_time'));
            // 查询样本表的分析时间，关联到家系表
            $query->whereHas('samples', function ($q) use ($analysisTime) {
                $q->whereBetween('analysis_time', [$analysisTime[0], $analysisTime[1]]);
            });
        }

        if ($request->has('report_time') && $request->input('report_time') != '') {
            $reportTime = explode(' - ', $request->input('report_time'));
            // 家系表中的报告时间
            $query->whereBetween('report_time', [$reportTime[0], $reportTime[1]]);
        }

        if ($request->has('check_result') && $request->input('check_result') != '') {
            // 样本表中的检查结果
            $query->whereHas('samples', function ($q) use ($request) {
                $q->where('check_result', $request->input('check_result'));
            });
        }

        if ($request->has('analysis_result') && $request->input('analysis_result') != '') {
            // 查询样本中的分析结果，关联到家系表
            $query->whereHas('samples', function ($q) use ($request) {
                $q->where('analysis_result', $request->input('analysis_result'));
            });
        }

        if ($request->has('report_result') && $request->input('report_result') != '') {
            // 查询报告结果
            $query->where('report_result', $request->input('report_result'));
        }
        if ($request->has('family_name_like') && $request->input('family_name_like') != '') {
            // 查询报告结果
            $query->where('name', 'like', '%' . $request->input('family_name_like') . '%');
        }

        $total = $query->count();
        $data = $query->orderBy('id', 'desc')->paginate((int)$request->input('limit', 10))->items();

        // 处理样本类型名称
        foreach ($data as $item) {
            foreach ($item->samples as $k => $sample) {
                $item->samples[$k]->sample_type_name = Sample::SAMPLE_TYPE_MAP_NAMES[$sample->sample_type];
            }
        }

        return [
            'total' => $total,
            'data' => $data
        ];
    }

    /**
     * 获取家庭详情
     *
     * @param Request $request
     * @return Object|null
     */
    public function getFamily(Request $request)
    {
        $family = Family::with('samples')->find($request->input('id'));
        if ($family) {
            $sampleTypes = [];
            foreach ($family->samples as $k => $sample) {
                $sample->sample_type_name = Sample::SAMPLE_TYPE_MAP_NAMES[$sample->sample_type] ?? '未知类型';
                $sampleTypes[$sample->sample_type] = $sample;
            }
            $family->samples = $sampleTypes;
        }

        return $family;
    }

    /**
     * 家系报告分析运行
     * 
     * @param Request $request
     * @return bool
     */
    public function analysisRun(Request $request)
    {
        // 验证家系信息
        $family = Family::with(['samples'])->find($request->input('family_id'));
        throw_unless($family, new ApiException(1, '家庭信息不存在！'));
        // 验证家系报告结果-仅未分析状态可运行
        $reportResult = Family::REPORT_RESULT_MAP_NAMES[$family->report_result] ?? '';
        throw_if($family->report_result != Family::REPORT_RESULT_UNKNOWN, new ApiException(1, $reportResult . '状态，不能操作运行！'));
        // 检查各样本的分析结果
        $samples = $family->samples;
        $analysisResult = array_unique(array_column($samples->toArray(), 'analysis_result'));
        throw_if(count($analysisResult) > 1, new ApiException(1, '此家系内样本的分析结果含未完成状态，不能运行！'));
        throw_if(count($analysisResult) == 0, new ApiException(1, '此家系内的样本分析结果为空，不能运行！'));
        $sampleAnalysisResult = Sample::ANALYSIS_RESULT_MAP_NAMES[$analysisResult[0]] ?? '';
        throw_if($analysisResult[0] != Sample::ANALYSIS_RESULT_SUCCESS, new ApiException(1, '此家系内的样本的分析结果为' . $sampleAnalysisResult . '，不能运行！'));

        DB::beginTransaction();
        try {
            // 更新家系报告结果
            $family->report_result = Family::REPORT_RESULT_ANALYZING;
            $family->save();
            // 请求分析接口
            FamilyAnalysisRunJob::dispatch($family->id)->onQueue('family_analysis_run');
            // 记录日志
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 家系报告分析重运行
     * 
     * @param Request $request
     * @return bool
     */
    public function analysisRerun(Request $request)
    {
        // 验证家系信息
        $family = Family::find($request->input('family_id'));
        throw_unless($family, new ApiException(1, '家庭信息不存在！'));
        // 验证家系报告结果-仅未分析状态可运行
        $reportResult = Family::REPORT_RESULT_MAP_NAMES[$family->report_result] ?? '';
        throw_if($family->report_result != Family::REPORT_RESULT_FAIL, new ApiException(1, $reportResult . '状态，不能操作重运行！'));

        DB::beginTransaction();
        try {
            // 更新家系报告结果
            $family->report_result = Family::REPORT_RESULT_SUCCESS;
            $family->save();
            // 请求重分析接口
            FamilyAnalysisRunJob::dispatch($family->id)->onQueue('family_analysis_run');
            // 记录日志
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 获取TSV数据
     *
     * @param int $familyId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getTsvData($familyId, $request)
    {
        try {
            $type = $request->input('type', '');

            $family = Family::with('samples')->findOrFail($familyId);
            if (!$family) {
                throw new ApiException(1, 'Family not found');
            }
            // 组装路径等相关参数
            /* $samples = $family->samples;
            $sampleTypes = array_column($samples->toArray(), 'sample_name', 'sample_type');
            $fatherSample = $sampleTypes[Sample::SAMPLE_TYPE_FATHER] ?? '';
            // $motherSample = $sampleTypes[Sample::SAMPLE_TYPE_MOTHER] ?? '';
            $childSample = $sampleTypes[Sample::SAMPLE_TYPE_CHILD] ?? ''; */

            $fatherSample = $request->input('father_sample', '');
            $childSample = $request->input('child_sample', '');
            // 组装路径
            $dataDir = config('data')['second_analysis_project'] . $fatherSample . '_vs_' . $childSample;
            // 文件后缀
            $fileExt = '';
            switch ($type) {
                // 简单报告数据
                case 'summary':
                    $fileExt = '.result.summary.tsv';
                    break;
                // SNP匹配表
                case 'report':
                    $fileExt = '.report.tsv';
                    break;
                // Y染色体排查
                case 'chrY':
                    $fileExt = '.chrY.result.tsv';
                    break;
                // 总表
                default:
                    $fileExt = '.result.tsv';
                    break;                    
            }
            $tsvFilePath = $dataDir . $fileExt;

            if (!file_exists($tsvFilePath)) {
                throw new ApiException(1, 'TSV file not found');
            }
            // 本地测试文件
            // $tsvFilePath = storage_path('a.tsv');

            switch ($type) {
                case 'summary':
                    $tsvData = $this->getSummareTsvFile($tsvFilePath, $request);
                    break;
                case 'report':
                    $tsvData = $this->getReportTsvFile($tsvFilePath, $request);
                    break;
                case 'chrY':
                    $tsvData = $this->getChrYTsvFile($tsvFilePath, $request);
                    break;
                default:
                    $tsvData = [];
                    // $tsvData = $this->parseTsvFile($tsvFilePath);
                    break;
            }
            return $tsvData;
        } catch (\Exception $e) {
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 获取TXT数据
     *
     * @param int $familyId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getTxtData($familyId, $request)
    {
        try {
            $type = $request->input('type', '');

            $family = Family::with('samples')->findOrFail($familyId);
            if (!$family) {
                throw new ApiException(1, 'Family not found');
            }
            // 组装路径等相关参数
            /* $samples = $family->samples;
            $sampleTypes = array_column($samples->toArray(), 'sample_name', 'sample_type');
            $fatherSample = $sampleTypes[Sample::SAMPLE_TYPE_FATHER] ?? '';
            // $motherSample = $sampleTypes[Sample::SAMPLE_TYPE_MOTHER] ?? '';
            $childSample = $sampleTypes[Sample::SAMPLE_TYPE_CHILD] ?? ''; */

            $sample_name = $request->input('sample_name', '');
            if('child_sample' == $sample_name){
                $sampleName = $request->input('child_sample', '');
            }else{
                $sampleName = $request->input('father_sample', '');
            }
            // 组装路径
            $dataDir = config('data')['qc_data_dir'] . $sampleName;
            // 文件后缀
            $fileExt = '';
            switch ($type) {
                // 样本质控表
                case 'qc':
                    $fileExt = '.qc.txt';
                    break;
                // 总表
                default:
                    $fileExt = '';
                    break;                    
            }
            $txtFilePath = $dataDir . $fileExt;

            if (!file_exists($txtFilePath)) {
                throw new ApiException(1, 'TXT file not found');
            }
            // 本地测试文件
            // $tsvFilePath = storage_path('a.tsv');

            switch ($type) {
                case 'qc':
                    $txtData = $this->getQcTxtFile($txtFilePath, $request);
                    break;
                default:
                    $txtData = [];
                    break;
            }
            return $txtData;
        } catch (\Exception $e) {
            throw new ApiException(1, $e->getMessage());
        }
    }

    /**
     * 样本质控表
     *
     * @param [type] $filePath
     * @return void
     */
    protected function getQcTxtFile($filePath, $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $offset = ($page - 1) * $limit;

        // 读取文件内容
        $fileContent = file_get_contents($filePath);
        $lines = array_filter(explode("\n", $fileContent)); // 过滤空行

        // 获取表头（第一行）
        $header = explode("\t", trim(array_shift($lines)));

        // 处理数据行
        $collection = collect($lines);
        $total = $collection->count();

        $verticalData = $collection->slice($offset, $limit)
            ->map(function ($line) use ($header) {
                $values = explode("\t", trim($line));

                return collect($header)->map(function ($column, $index) use ($values) {
                    return [
                        'column' => $column,
                        'value' => $values[$index] ?? null
                    ];
                });
            })
            ->flatten(1) // 将多维集合展平
            ->filter()
            ->values();
        return [
            'count' => $total, // 总记录数 = 原行数 * 列数
            'data' => $verticalData
        ];
    }
/**
     * 简单报告表格
     *
     * @param [type] $filePath
     * @return void
     */
    protected function getSummareTsvFile($filePath, $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $offset = ($page - 1) * $limit;

        $search = [];

        // 将数组转换为集合
        $data = collect($this->parseTsvFile($filePath))->when($search, function ($collection) use ($search) {
            return $collection->filter(function ($item) use ($search) {
                // 根据搜索条件过滤数据
                // return Str::contains($item['title'], $search); // 搜索title字段
            });
        });

        // 分页处理
        $paginatedData = $data->slice($offset, $limit)->values();
        foreach ($paginatedData as $kk => $item) {
            foreach ($item as $key => $value) {
                // 特殊处理错配位点数
                if ('A/N' == $key) {
                    $replaceKey = str_replace('/', '_', $key);
                    $replaceValue = explode('/', $value);
                    $item[$replaceKey] = $replaceValue[1] ?? '';
                    unset($item[$key]);
                }
                // 特殊处理父本
                if ('Pairs' == $key) {
                    $item[$key] = explode('_vs_', $value)[0] ?? '';
                }
            }
            $paginatedData[$kk] = $item;
        }

        return [
            'count' => $data ? $data->count() : 0,
            'data' => $paginatedData
        ];
    }


    /**
     * SNP表格
     *
     * @param [type] $filePath
     * @return void
     */
    protected function getReportTsvFile($filePath, $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $offset = ($page - 1) * $limit;

        $search = [];

        // 将数组转换为集合
        $data = collect($this->parseTsvFile($filePath))->when($search, function ($collection) use ($search) {
            return $collection->filter(function ($item) use ($search) {
                // 根据搜索条件过滤数据
                // return Str::contains($item['title'], $search); // 搜索title字段
            });
        });

        // 分页处理
        $paginatedData = $data->slice($offset, $limit)->values();
        // 确保 $data 是集合或数组
        $count = is_array($data) || $data instanceof \Countable ? $data->count() : 0;

        return [
            'count' => $count,
            'data' => $paginatedData
        ];
    }

    /**
     * chrY表格
     *
     * @param [type] $filePath
     * @return void
     */
    protected function getChrYTsvFile($filePath, $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $offset = ($page - 1) * $limit;

        $search = [];

        // 将数组转换为集合
        $data = collect($this->parseTsvFile($filePath))->when($search, function ($collection) use ($search) {
            return $collection->filter(function ($item) use ($search) {
                // 根据搜索条件过滤数据
                // return Str::contains($item['title'], $search); // 搜索title字段
            });
        });

        // 分页处理
        $paginatedData = $data->slice($offset, $limit)->values();
        // 确保 $data 是集合或数组
        $count = is_array($data) || $data instanceof \Countable ? $data->count() : 0;

        return [
            'count' => $count,
            'data' => $paginatedData
        ];
    }

    /**
     * 解析TSV文件-公共方法
     *
     * @param string $filePath
     * @return array
     */
    protected function parseTsvFile($filePath)
    {
        if (!file_exists($filePath)) {
            return []; // 文件不存在时返回空数组
        }

        $rows = array_map('str_getcsv', file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), array_fill(0, count(file($filePath)), "\t"));
        if (empty($rows)) {
            return [];
        }
        $header = array_shift($rows);

        // 处理表头和表体长度不一致的情况
        $headerCount = count($header);
        $rowCount =  isset($rows) ? count($rows[0] ?? 0) : 0;
        $minLenght = 0;
        if ($headerCount != $minLenght) {
            $minLenght = min($headerCount, $rowCount);
            $header = array_slice($header, 0, $minLenght);
        }

        $data = [];

        foreach ($rows as $row) {
            // 长度不一致处理
            if ($minLenght > 0) {
                $row = array_slice($row, 0, $minLenght);
            }

            $data[] =  array_combine($header, $row);
        }

        return $data;
    }

    public function getPicData($familyId, $request)
    {
        $type = $request->input('type', '');

        $family = Family::with('samples')->findOrFail($familyId);
        if (!$family) {
            throw new \Exception('Family not found');
        }
        // 组装路径等相关参数
        /* $samples = $family->samples;
        $sampleTypes = array_column($samples->toArray(), 'sample_name', 'sample_type');
        $fatherSample = $sampleTypes[Sample::SAMPLE_TYPE_FATHER] ?? '';
        // $motherSample = $sampleTypes[Sample::SAMPLE_TYPE_MOTHER] ?? '';
        $childSample = $sampleTypes[Sample::SAMPLE_TYPE_CHILD] ?? ''; */

        $fatherSample = $request->input('father_sample', '');
        $childSample = $request->input('child_sample', '');
        // 组装路径
        $dataDir = config('data')['second_analysis_project'] . $fatherSample . '_vs_' . $childSample;
        // 文件后缀
        $fileExt = '';
        switch ($type) {
            // 简单报告数据
            case 'qc':
                $fileExt = '.qc.png';
                break;
            // SNP匹配表
            case 'child':
                $fileExt = '.child.png';
                break;
            // 胎儿浓度图
            case 'child_qc':
                // 样本
                $sample = Sample::where('sample_name', $childSample)->where('family_name', $family->name)->first();
                $dataDir = config('data')['analysis_project'] . $sample->output_dir . '/' . $childSample;
                $fileExt = '.base_qc.png';
                break;
            default:
                break;
        }
        $picFilePath = $dataDir . $fileExt;

        return $picFilePath;
    }

    /**
     * 
     */
    public function searchData($id, $request)
    {
        $newMontherSample = $request->input('mother_sample', '');
        $newFatherSample = $request->input('father_sample', '');
        $newChildSample = $request->input('child_sample', '');
        $newr = $request->input('slider_r', '');
        $news = $request->input('slider_s', '');
        $family = Family::with('samples')->find($id);
        if (!$family) {
            throw new \Exception('Family not found');
        }

        $newSamples = Sample::whereIn('sample_name', [$newFatherSample, $newChildSample, $newMontherSample])
            ->where('analysis_result', Sample::ANALYSIS_RESULT_SUCCESS)
            ->pluck('output_dir', 'sample_name')
            ->toArray();

        $diffSamples = array_diff(
            array_filter([$newFatherSample, $newChildSample, $newMontherSample]),
            array_keys($newSamples)
        );
        if (!empty($diffSamples)) {
            throw new \Exception('样本不存在或未分析成功：' . implode(',', $diffSamples));
        }
        
        // 是否是新家系
        $isNewFamily = false;
        foreach ($family->samples as $sample) { 
            if(!in_array($sample->sample_name, [$newFatherSample, $newChildSample, $newMontherSample])) {
                $isNewFamily = true;
                break;
            }
        }
        // 执行分析

        return $this->run($newFatherSample, $newSamples[$newFatherSample], $newChildSample, $newSamples[$newChildSample], $newMontherSample, $newSamples[$newMontherSample] ?? '', $newr, $news, $isNewFamily,  $family);
    }

    /**
     * 下载表格
     */
    public function downloadTable($request)
    {
        // 组装路径
        $fileName = $request->input('father_sample', '') . '_vs_' . $request->input('child_sample', '');
        $dataDir = config('data')['second_analysis_project'] . $fileName;
        // $dataDir = config('data')['second_analysis_project'] . 'PPA20250300041F1-2_vs_PPA20250300041S1.report';

        $type = $request->input('type', '');
        $keys = $header = [];
        switch ($type) {
            // 简单报告数据
            case 'summary':
                $fileExt = '.result.summary.tsv';
                break;
            // SNP匹配表
            case 'report':
                $keys = [
                    'ID',
                    'CHR',
                    'GT_Father',
                    'GT_Mother',
                    'GT_Baby',
                    'Match',
                ];
                $header   = [
                    '检测位点编号',
                    '染色体',
                    '父本基因型',
                    '母本基因型',
                    '胎儿基因型',
                    '是否错配'
                ];
                $fileExt = '.report.tsv';
                break;
            // Y染色体排查
            case 'chrY':
                $keys = [
                    'ID',
                    'Chr',
                    'Loc',
                    'RefBase',
                    'AltBase',
                    'GT_Father',
                    'GT_Baby',
                    'Deciside',
                    'Depth',
                ];
                $header   = [
                    'ID',
                    'Chr',
                    'Loc',
                    'RefBase',
                    'AltBase',
                    'GT_Father',
                    'GT_Baby',
                    'Deciside',
                    'Depth',
                ];
                $fileExt = '.chrY.result.tsv';
                break;
            // 总表
            default:
                $fileExt = '.result.tsv';
                break;                    
        }

        $data = $this->parseTsvFile($dataDir . $fileExt);


        $final_name = generateFileSavePath(public_path('/static/download/files/'), $fileName. $fileExt . $type . '.xls');
        $excel      = new Excel(['save_path' => $final_name]);
        $excel->generateXls($data, $header, $keys);

        return $final_name;
    }

    public function run(
        $fatherSample,
        $fatherOutputDir,
        $childSample,
        $childOutputDir,
        $motherSample = '',
        $motherOutputDir = '',
        $r = 4,
        $s = 0.008,
        $isNewFamily = false,
        $family
    ) {
        $analysisProject = config('data')['analysis_project']; // 本地样本分析目录
        $secondAnalysisProject = config('data')['second_analysis_project']; // 二级分析目录
        $secondAnalysisProjectDir = escapeshellarg($secondAnalysisProject); //转义后的二级分析目录

        // 胎儿编号
        $childPath = escapeshellarg($analysisProject . $childOutputDir . '/' . $childSample . '.base.txt');
        // 母本编号-可能为空
        $motherPath = '';
        if (!empty($motherSample)) {
            $motherPath = ' m ' . escapeshellarg($analysisProject . $motherOutputDir . '/' . $motherSample . '.base.txt');
        }
        // 父本编号
        $fatherPath = escapeshellarg($analysisProject . $fatherOutputDir . '/' . $fatherSample . '.base.txt'); //绝对路径

        $commandPl = config('data')['family_analysis_run_command_pl'];

        $command = "cd {$secondAnalysisProjectDir} && " . $commandPl . " -r {$r} -s {$s} -b {$childPath}{$motherPath} -f {$fatherPath} 2>log";
        Log::info('search-command:' . $command);
        // 执行shell命令
        putenv(config('data')['perl_path']);
        putenv(config('data')['perl_perl5ltb']);
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            // 验证只是修改滑窗，则要修改数据库
            if (!$isNewFamily) {
                $family->r = $r;
                $family->s = $s;
                $family->save();
            }
            // 符合条件-更新检测结果状态为成功
            Log::info('search-success');
            return true;
        } else {
            Log::info('search-fail');
            return false;
        }
    }

    public function fatherSearchData($id, $request)
    {
        $newFatherSample = $request->input('father_sample', '');
        $newChildSample = $request->input('child_sample', '');
        $fatherNum = $request->input('father_num', 30);
        $newr = $request->input('slider_r', '');
        $news = $request->input('slider_s', '');
        $family = Family::with('samples')->find($id);
        if (!$family) {
            throw new \Exception('Family not found');
        }
        $familyFatherName = '';
        $familyChild = '';
        foreach ($family->samples as $sample) {
            if ($sample->sample_type == Sample::SAMPLE_TYPE_FATHER) {
                $familyFatherName = $sample->sample_name;
            }
            if ($sample->sample_type == Sample::SAMPLE_TYPE_CHILD) {
                $familyChild = $sample;
            }
        }
        if (empty($familyChild)) {
            throw new \Exception('无胎儿样本');
        }
        $fatherSamples = Sample::whereNotIn('sample_name', [$newFatherSample, $familyFatherName])
            ->where('sample_type', Sample::SAMPLE_TYPE_FATHER)
            ->where('analysis_result', Sample::ANALYSIS_RESULT_SUCCESS)
            ->orderBy('id', 'desc')
            ->take($fatherNum)
            ->get(['output_dir', 'sample_name']);

        if ($fatherSamples->isEmpty()) {
            throw new \Exception('无相近的父本');
        }
        $fatherSampleNames = [];
        $jobs = [];
        foreach ($fatherSamples as $fatherSample) {
            // 已生成过，不再运行，
            $dataDir = config('data')['second_analysis_project'] . $fatherSample->sample_name . '_vs_' . $familyChild['sample_name'];
            // 文件后缀
            $fileExt = '.result.summary.tsv';
            $tsvFilePath = $dataDir . $fileExt;
            // 去重
            if(in_array($fatherSample->sample_name, $fatherSampleNames)){
                continue;
            }
            $fatherSampleNames[] = $fatherSample->sample_name;
            // 已生成过，不再运行，
            if (file_exists($tsvFilePath)) {
                continue;
            }
            // 放job队列
            $jobs[] =  new ConcurrentFamilyAnalysisJob(
                $fatherSample->sample_name, 
                $fatherSample->output_dir,
                $familyChild['sample_name'],
                $familyChild['output_dir'],
                $newr,
                $news,
                $family
            );
            // 执行分析
            // if ($this->run($fatherSample->sample_name, $fatherSample->output_dir, $familyChild['sample_name'], $familyChild['output_dir'], '', '', $newr, $news, true,  $family)) {
            //     $fatherSampleNames[] = $fatherSample->sample_name;
            // }
        }
        $batchId = '';
        if(!empty($jobs)) {
            // 批量执行
            $batch = Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // 所有任务完成后的处理
                Log::info('bus::batch-所有任务完成id:' . $batch->id, (array)$batch);
            })
            ->catch(function (Batch $batch, Exception $e) {
                Log::info('bus::batch-任务失败id:' . $batch->id.';'.$e->getMessage(), (array)$batch);
                // 批次中第一个任务失败时执行
            })->finally(function (Batch $batch) {
                // 无论成功或失败都会执行
                Log::info('bus::batch-finally:' , (array)$batch);
            })
            ->onConnection('redis')  // 明确指定使用redis连接
            ->onQueue('father_filter')       // 可选，指定队列优先级
            ->dispatch(); // 指定队列

            $batchId = $batch->id; // 获取批次ID
        }
        // 有批量处理-获取批次完成进度
        if(!empty($batchId)){
            $startTime = time();
            progress:
            $batch = Bus::findBatch($batchId);
            if ($batch) {
                $progress = $batch->progress();
                Log::info('bus::batch-progress:' . $progress);
                // 未完成时循环
                if ($progress < 100 && (time() - $startTime) < 60) {
                    goto progress;
                }
            } else {
                Log::info('bus::batch-not-found:' . $batchId);
            }
        }
        return [
            'father_sample_names' => $fatherSampleNames,
            'batch_id' => $batchId,
        ];
    }
    

    /**
     * 父本排查表格
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function fatherSearchTable($request)
    {
        try {
            $childSample = $request->input('child_sample', '');
            $fatherSamples = $request->input('father_sample_names', []);
            $returnData = [];
            foreach ($fatherSamples as $fatherSample) {
                // 组装路径
                $dataDir = config('data')['second_analysis_project'] . $fatherSample . '_vs_' . $childSample;
                // 文件后缀
                $fileExt = '.result.summary.tsv';
                $tsvFilePath = $dataDir . $fileExt;

                if (!file_exists($tsvFilePath)) {
                    throw new ApiException(1, 'TSV file not found');
                }
                // 本地测试文件
                // $tsvFilePath = storage_path('a.tsv');

                $search = [];

                // 将数组转换为集合
                $data = collect($this->parseTsvFile($tsvFilePath))->when($search, function ($collection) use ($search) {
                    return $collection->filter(function ($item) use ($search) {
                        // 根据搜索条件过滤数据
                        // return Str::contains($item['title'], $search); // 搜索title字段
                    });
                });

                // 分页处理
                $paginatedData = $data->values();
                foreach ($paginatedData as $kk => $item) {
                    foreach ($item as $key => $value) {
                        // 特殊处理错配位点数
                        if ('A/N' == $key) {
                            $replaceKey = str_replace('/', '_', $key);
                            $replaceValue = explode('/', $value);
                            $item[$replaceKey] = $replaceValue[1] ?? '';
                            unset($item[$key]);
                        }
                        // 特殊处理父本
                        if ('Pairs' == $key) {
                            $item[$key] = explode('_vs_', $value)[0] ?? '';
                        }
                    }
                    $paginatedData[$kk] = $item;
                }
                $returnData = array_merge($returnData, $paginatedData->toArray());
            }

            return [
                'count' => $returnData ? count($returnData) : 0,
                'data' => $returnData
            ];
        } catch (\Exception $e) {
            throw new ApiException(1, $e->getMessage());
        }
    }
    /**
     * 同一认定
     *
     * @param Request $request
     * @return bool
     */
    public function unityRun($request)
    {
        $sampleAId = $request->input('sampleAId', 0);
        throw_if($sampleAId < 1 , new ApiException(1, '请选目标样本'));
        $sampleIdStr = $request->input('sampleIds', '');
        $sampleIds = array_unique(explode(',', $sampleIdStr));
        throw_if(empty($sampleIds), new ApiException(1, '请选至少1个样本'));

        // 将sampleAId加入到sampleIds中 
        if (!in_array($sampleAId, $sampleIds)) {
            $sampleIds[] = $sampleAId;
        }

        $samples = Sample::whereIn('id', $sampleIds)
            ->where('sample_type', Sample::SAMPLE_TYPE_FATHER)
            ->where('analysis_result', Sample::ANALYSIS_RESULT_SUCCESS)
            ->pluck('sample_name', 'id');

        if ($samples->isEmpty()) {
            throw new \Exception('未找到匹配父本数据');
        }
        // sampleAId名称,单个样本名称
        $sampleAName = $samples->get($sampleAId);
        // 获取所有样本名称，除去sampleAId名称
        $sampleIds = array_values(array_diff($sampleIds, array($sampleAId)));
        $sampleBNames = $samples->filter(function ($key, $item) use ($sampleAId) {
            return $key != $sampleAId;
        });
        // 用都好分隔sampleBNames
        $sampleBNames = implode(',', $sampleBNames->all());
        
        $secondAnalysisProject = config('data')['analysis_project']; // 一级分析目录
        $secondAnalysisProjectDir = escapeshellarg($secondAnalysisProject); //转义后的二级分析目录

        $commandPl = config('data')['family_synonym_run_command_pl']; // 运行perl脚本
        // 生成地址
        $generateDir = $sampleAName.'_unity_out.tsv';

        $command = "cd {$secondAnalysisProjectDir} && " . $commandPl . " -b {$sampleAName} -f {$sampleBNames} -o {$generateDir} 2>log";
        Log::info('同一认定-command:' . $command);
        // 执行shell命令
        putenv(config('data')['perl_path']);
        putenv(config('data')['perl_perl5ltb']);
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            // 符合条件-更新检测结果状态为成功
            Log::info('search-success');
            return true;
        } else {
            Log::info('search-fail');
            return false;
        }
    }
    
    /**
     * 同一认定表格
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function unityTable($request)
    {
        try {
            $fatherSample = Sample::where('id', $request->sampleAId)->first();
            $returnData = [];
            // 组装路径
            $dataDir = config('data')['analysis_project'] . $fatherSample->sample_name;
            // 文件后缀
            $fileExt = '_unity_out.tsv';
            $tsvFilePath = $dataDir . $fileExt;

            if (!file_exists($tsvFilePath)) {
                throw new ApiException(1, 'TSV file not found');
            }
            // 本地测试文件
            // $tsvFilePath = storage_path('a.tsv');

            $search = [];

            // 将数组转换为集合
            $data = collect($this->parseTsvFile($tsvFilePath))->when($search, function ($collection) use ($search) {
                return $collection->filter(function ($item) use ($search) {
                    // 根据搜索条件过滤数据
                    // return Str::contains($item['title'], $search); // 搜索title字段
                });
            });

            $returnData = $data->values();
            foreach ($returnData as $kk => $item) {
                if(!isset($item['Sample_A'])){
                    unset($returnData[$kk]);
                }
            }

            return [
                'count' => $returnData ? count($returnData) : 0,
                'data' => $returnData
            ];
        } catch (\Exception $e) {
            throw new ApiException(1, $e->getMessage());
        }
    }
}
