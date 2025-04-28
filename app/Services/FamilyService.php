<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\FamilyAnalysisRunJob;
use App\Models\Family;
use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyService extends BaseService
{
    public function __construct()
    {
    }

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
            'analysis_result_map_names' => Sample::ANALYSIS_RESULT_MAP_NAMES
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

        $total = $query->count();
        $data = $query->paginate((int)$request->input('limit', 10))->items();

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
        $reportResult = Family::REPORT_RESULT_MAP_NAMES[$family->report_result]??'';
        throw_if($family->report_result != Family::REPORT_RESULT_UNKNOWN, new ApiException(1, $reportResult.'状态，不能操作运行！'));
        // 检查各样本的分析结果
        $samples = $family->samples;
        $analysisResult = array_unique(array_column($samples->toArray(), 'analysis_result'));
        throw_if(count($analysisResult) > 1, new ApiException(1, '此家系内样本的分析结果含未完成状态，不能运行！'));
        throw_if(count($analysisResult) == 0, new ApiException(1, '此家系内的样本分析结果为空，不能运行！'));
        $sampleAnalysisResult = Sample::ANALYSIS_RESULT_MAP_NAMES[$analysisResult[0]] ?? '';
        throw_if($analysisResult[0] != Sample::ANALYSIS_RESULT_SUCCESS, new ApiException(1, '此家系内的样本的分析结果为'.$sampleAnalysisResult.'，不能运行！'));
        
        // DB::beginTransaction();
        try {
            // TODO:请求分析接口
            dispatch(new FamilyAnalysisRunJob($family->id))->onQueue('family_analysis_run');
            // 更新家系报告结果
            $family->report_result = Family::REPORT_RESULT_ANALYZING;
            $family->save();
            // 记录日志
            // DB::commit();
            return true;
        }catch (\Exception $e) {
            // DB::rollBack();
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
        $family = Family::find($request->input('id'));
        throw_unless($family, new ApiException(1, '家庭信息不存在！'));
        // 验证家系报告结果-仅未分析状态可运行
        $reportResult = Family::REPORT_RESULT_MAP_NAMES[$family->report_result]??'';
        throw_if($family->report_result != Family::REPORT_RESULT_FAIL, new ApiException(1, $reportResult.'状态，不能操作重运行！'));
        
        DB::beginTransaction();
        try {
            // TODO:请求重分析接口
            // 更新家系报告结果
            $family->report_result = Family::REPORT_RESULT_SUCCESS;
            $family->save();
            // 记录日志
            DB::commit();
            return true;
        }catch (\Exception $e) {
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
        $type = $request->input('type', '');

        $family = Family::with('samples')->findOrFail($familyId);
        if (!$family) {
            throw new \Exception('Family not found');
        }
        // 组装路径等相关参数
        $samples = $family->samples;
        $sampleTypes = array_column($samples->toArray(), 'sample_name', 'sample_type');
        $fatherSample = $sampleTypes[Sample::SAMPLE_TYPE_FATHER] ?? '';
        // $motherSample = $sampleTypes[Sample::SAMPLE_TYPE_MOTHER] ?? '';
        $childSample = $sampleTypes[Sample::SAMPLE_TYPE_CHILD] ?? '';
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
            // 总表
            default:
                $fileExt = '.result.tsv';
                break;
        }
        $tsvFilePath = $dataDir.$fileExt;

        if (!file_exists($tsvFilePath)) {
            throw new \Exception('TSV file not found');
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
            default:
                $tsvData = $this->parseTsvFile($tsvFilePath);
                break;
        }

        return $tsvData;
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
                if('A/N' == $key){
                    $replaceKey = str_replace('/', '_', $key);
                    $replaceValue = explode('/', $value);
                    $item[$replaceKey] = $replaceValue[1] ?? '';
                    unset($item[$key]);
                }
                // 特殊处理父本
                if('Pairs' == $key){
                    $item[$key] = explode('_vs_',$value)[0] ?? '';
                }
            }
            $paginatedData[$kk] = $item;
        }

        return [
            'total' => $data->count(),
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

        return [
            'total' => $data->count(),
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
        $rows = array_map('str_getcsv', file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), array_fill(0, count(file($filePath)), "\t"));
        $header = array_shift($rows);
        
        $data = [];

        foreach ($rows as $row) {
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
        $samples = $family->samples;
        $sampleTypes = array_column($samples->toArray(), 'sample_name', 'sample_type');
        $fatherSample = $sampleTypes[Sample::SAMPLE_TYPE_FATHER] ?? '';
        // $motherSample = $sampleTypes[Sample::SAMPLE_TYPE_MOTHER] ?? '';
        $childSample = $sampleTypes[Sample::SAMPLE_TYPE_CHILD] ?? '';
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
            // 总表
            default:
                break;
        }
        $picFilePath = $dataDir.$fileExt;

        return $picFilePath;
    }

    /**
     * 
     */
    public function searchData($id, $request)
    {
        throw new \Exception('处理中...');
        $newMontherSample = $request->input('mother_sample', '');
        $newFatherSample = $request->input('father_sample', '');
        $newChildSample = $request->input('child_sample', '');
        $newr = $request->input('slider_r', '');
        $news = $request->input('slider_s', '');
        $family = Family::with('samples')->find($id);
        if (!$family) {
            throw new \Exception('Family not found');
        }
        $samples = $family->samples;
        $sampleTypes = array_column($samples->toArray(),'sample_name' ,'sample_type');
        $fatherSample = $sampleTypes[Sample::SAMPLE_TYPE_FATHER] ?? '';
        $motherSample = $sampleTypes[Sample::SAMPLE_TYPE_MOTHER] ?? '';
        $childSample = $sampleTypes[Sample::SAMPLE_TYPE_CHILD] ?? '';
        // 比较传过来的父本与当前的父本，传过来的子本与当前的子本是否一致，不一致则查询，是否已组建家系，未组建家系则创建家系
        if ($fatherSample != $newFatherSample || $childSample != $newChildSample) {
            $newSamples = Sample::whereIn('sample_name', [$newFatherSample, $newChildSample])
                ->where('analysis_result', Sample::ANALYSIS_RESULT_SUCCESS)->pluck('sample_name', 'id')->toArray();
            $diffSamples = array_diff([$newFatherSample, $newChildSample], $newSamples);
            if (!empty($diffSamples)) {
                throw new \Exception('样本不存在或未分析成功：' . implode(',', $diffSamples));
            }

            // 查询家系与样本关系表(families_samples)，用2个样本查询是不是属于同一个家系

            $family = Family::where('father_sample', $fatherSample)
                ->where('child_sample', $childSample)
                ->first();

            if (!$family) {
                $family = Family::create([
                    'father_sample' => $fatherSample,
                    'child_sample' => $childSample,
                ]);
            }
        }

    }
}