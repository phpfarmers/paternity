<?php

namespace App\Console\Commands;

use App\Jobs\FamilyAnalysisRunJob;
use App\Models\Family;
use Illuminate\Console\Command;
use App\Models\Sample;
use Illuminate\Support\Facades\Log;

class FamilyAnalysisRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'family:analysis:run {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动分析家系';

    /**
     * 超时时间
     * 默认1小时
     */
    protected $timeout = 3600; // 1小时

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('自动-家系分析开始：' . date('Y-m-d H:i:s'));
        Log::info('自动-家系分析开始：' . date('Y-m-d H:i:s'));
        $id = $this->argument('id') ?? 0;
        $families = Family::where('report_result', '!=', Family::REPORT_RESULT_SUCCESS);
        // 指定id-前端操作
        if ($id > 0) {
            $families = $families->where('id', $id);
        } else {
            $families = $families->where('report_result', Family::REPORT_RESULT_UNKNOWN);
        }
        $families = $families->orderBy('report_times', 'asc')
            ->orderBy('id', 'asc')
            ->limit(100)->get();

        if ($families->isEmpty()) {
            $this->info('自动-没有要分析的家系：' . date('Y-m-d H:i:s'));
            Log::info('自动-没有要分析的家系：' . date('Y-m-d H:i:s'));
            return 0;
        }
        // 获取样本信息
        $samples = Sample::leftJoin('families_samples', 'families_samples.sample_id', '=', 'samples.id')
            ->whereIn('families_samples.family_id', $families->pluck('id')->toArray())
            ->get()->toArray();
        // 将样本信息用家系id分组
        $samplesGroupByFamilyId = [];
        foreach ($samples as $sample) {
            $samplesGroupByFamilyId[$sample['family_id']][$sample['sample_type']] = $sample;
        }

        try {
            foreach ($families as $family) {
                $this->info('自动-家系分析开始：' . $family->id . '-' . $family->name . '-' . date('Y-m-d H:i:s'));
                Log::info('自动-家系分析开始：' . $family->id . '-' . $family->name . '-' . date('Y-m-d H:i:s'));
                // 次数大于3000，标记为失败
                if ($family->report_times > 3000) {
                    $this->info('自动-家系分析：' . $family->name . '，次数大于3000，标记为失败');
                    Log::info('自动-家系分析：' . $family->name . '，次数大于3000，标记为失败');
                    $family->report_result = Family::REPORT_RESULT_FAIL;
                    $family->save();
                    continue;
                }
                
                // 家系分析变为分析中
                $family->report_result = Family::REPORT_RESULT_ANALYZING;
                $family->report_times  += 1;
                $family->save();
                if (!isset($samplesGroupByFamilyId[$family->id]) || count($samplesGroupByFamilyId[$family->id]) < 2) {
                    $this->info('自动-家系分析：' . $family->name . '，样本分析完成数量不正确');
                    Log::info('自动-家系分析：' . $family->name . '，样本分析完成数量不正确');
                    $family->report_result = Family::REPORT_RESULT_FAIL;
                    $family->save();
                    continue;
                }
                // 判断 有无样本未分析
                $canAnalysis = true;
                foreach ($samplesGroupByFamilyId[$family->id] as $sample) {
                    if ($sample['analysis_result'] != Sample::ANALYSIS_RESULT_SUCCESS) {
                        $this->info('自动-家系分析家系：' . $family->name . '，样本：' . $sample['sample_name'] . '-未分析');
                        Log::info('自动-家系分析：' . $family->name . '，样本:' . $sample['sample_name'] . '-未分析');
                        $canAnalysis = false;
                        break;
                    }
                }
                if (!$canAnalysis) {
                    // 有未分析的样本，重新放入队列
                    if($family->report_result == Family::REPORT_RESULT_ANALYZING){
                        $family->report_result = Family::REPORT_RESULT_UNKNOWN;
                        $family->save();
                    }
                    continue;
                }
                // 脚本放队列统一处理
                FamilyAnalysisRunJob::dispatch($family->id)->onQueue('family_analysis_run');

                $this->info('自动-家系分析完成-' . $family->id . '-' . $family->name . '-' . date('Y-m-d H:i:s'));
                Log::info('自动-家系分析完成-' . $family->id . '-' . $family->name . '-' . date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('自动-家系分析出错：' . $e->getMessage());
            Log::info('自动-家系分析出错：' . $e->getMessage());
        }
        return 0;
    }
}
