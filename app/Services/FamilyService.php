<?php

namespace App\Services;

use App\Exceptions\ApiException;
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
            foreach ($family->samples as $k => $sample) {
                $family->samples[$k]->sample_type_name = Sample::SAMPLE_TYPE_MAP_NAMES[$sample->sample_type];
            }
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
        $family = Family::with(['samples'])->find($request->input('id'));
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
        
        DB::beginTransaction();
        try {
            // TODO:请求分析接口
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
}