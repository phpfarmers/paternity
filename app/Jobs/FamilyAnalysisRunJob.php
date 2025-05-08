<?php

namespace App\Jobs;

use App\Models\Family;
use App\Models\Sample;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FamilyAnalysisRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    // 重试次数
    public $tries = 10;
    public $timeout = 7200;
    /**
     * Create a new job instance.
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 实现家系分析逻辑
        // 例如：调用 shell 脚本或 PHP 函数处理下载
        Log::info("开始家系分析：{$this->id}-".date('Y-m-d H:i:s'));
        $this->runAnalysis($this->id);
        Log::info("家系分析结束：{$this->id}-".date('Y-m-d H:i:s'));
    }
    
    protected function runAnalysis($id): void
    {
        $families = Family::where('report_result', '!=', Family::REPORT_RESULT_SUCCESS);
        // 指定id-前端操作
        $families = $families->where('id', $id);
        $families = $families->orderBy('report_times', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1)->get();

        if ($families->isEmpty()) {
            Log::info('没有要分析的家系');
            return;
        }
        // 获取样本信息
        $samples = Sample::leftJoin('families_samples', 'families_samples.sample_id', '=', 'samples.id')
            ->whereIn('families_samples.family_id', $families->pluck('id')->toArray())
            ->get()->toArray();
        // 将样本信息用家系id分组
        $samplesGroupByFamilyId = [];
        $canAnalysis = true;
        foreach ($samples as $sample) {
            $samplesGroupByFamilyId[$sample['family_id']][$sample['sample_type']] = $sample;
            if ($sample['analysis_result'] != Sample::ANALYSIS_RESULT_SUCCESS) {
                Log::error('样本未分析成功，请先分析样本:' . $sample['sample_name']);
                $canAnalysis = false;
                break;
            }
        }
        if (!$canAnalysis) {
            Log::error('存在未分析成功的样本，请先分析后再执行分析');
            return;
        }

        try {
            foreach ($families as $family) {
                Log::info('家系分析开始：' . $family->id . '-' . date('Y-m-d H:i:s'));
                // 家系分析变为分析中
                $family->report_result = Family::REPORT_RESULT_ANALYZING;
                $family->report_times  += 1;
                $family->save();
                if (!isset($samplesGroupByFamilyId[$family->id]) || count($samplesGroupByFamilyId[$family->id]) < 2) {
                    Log::info('家系分析：' . $family->name . 
                    '，样本分析完成数量不正确，family_id:' . $family->id . 
                    ',samples:' . count($samplesGroupByFamilyId[$family->id]));
                    // 不符合条件-更新检测结果状态为失败
                    $family->report_result = family::REPORT_RESULT_FAIL;
                    $family->save();
                    continue;
                }
                // TODO:脚本
                // shell命令参数
                // cd {分析目录} && /path/script/parse_perbase.pl -r 2 -s 0.003 -n All.baseline.tsv -b /path/{胎儿编号}.base.txt -m /path/{母本编号}.base.txt -f /path/{父本编号}.base.txt -o {输出前缀名，可以是胎儿编号} && Rscript /path/script/cal.r --args {输出前缀名，可以是胎儿编号}.result.tsv > {输出前缀名，可以是胎儿编号}.summary
                // 分析目录-待沟通确定?
                $analysisProject = config('data')['analysis_project']; // 本地样本分析目录
                $secondAnalysisProject = config('data')['second_analysis_project']; // 二级分析目录
                $secondAnalysisProjectDir = escapeshellarg($secondAnalysisProject); //转义后的二级分析目录

                // 胎儿编号
                $childSample = $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_CHILD]['sample_name'];
                $childOutputDir = $analysisProject . $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_CHILD]['output_dir'];
                $childPath = escapeshellarg($childOutputDir . '/' . $childSample . '.base.txt');
                // 母本编号-可能为空
                $m = '';
                if (!empty($samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_MOTHER])) {
                    $motherSample = $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_MOTHER]['sample_name'];
                    $motherOutputDir = $analysisProject . $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_MOTHER]['output_dir'];
                    $m = ' -m '. escapeshellarg($motherOutputDir . '/' . $motherSample . '.base.txt');
                }
                // 父本编号
                $fatherSample = $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_FATHER]['sample_name'];
                $fatherOutputDir = $analysisProject . $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_FATHER]['output_dir'];
                $fatherPath = escapeshellarg($fatherOutputDir . '/' . $fatherSample . '.base.txt'); //绝对路径
                // $childTsv = escapeshellarg($childSample . '.result.tsv'); //采用默认
                // $childSummary = escapeshellarg($childSample . '.summary'); //采用默认
                $commandPl = config('data')['family_analysis_run_command_pl'];
                // $commandCalR = config('data')['family_analysis_run_command_call_r']; //采用默认
                // shell命令参数
                // $command = "cd /QinZiProject && ~/scripts/parse_perbase.pl -r 4 -s 0.008 -n /share/guoyuntao/workspace/QinZi_20241125/wbc_baseline_noumi_20250225/All.baseline.tsv -b AKT103-S.1G/AKT103-S.1G.base.txt -m parent_bases/AKT103-M.base.txt -f parent_bases/AKT103-F.base.txt -o AKT103-S.1G.xxx 2>log && Rscript /path/script/cal.r --args AKT103-S.1G.xxx.result.tsv > AKT103-S.1G.xxx.summary";
                // $command = "cd {$secondAnalysisProjectDir} && ".$commandPl." -r 4 -s 0.008 -n All.baseline.tsv -b {$childPath} -m {$motherPath} -f {$fatherPath} -o {$childSample} 2>log && Rscript ".$commandCalR." --args {$childTsv} > {$childSummary} 2>&1";
                $r = !empty($family->r) ? $family->r : escapeshellarg(config('data')['family_analysis_run_command_default_r']); // 默认r值
                $s = !empty($family->s) ? $family->s : escapeshellarg(config('data')['family_analysis_run_command_default_s']); // 默认s值
                $command = "cd {$secondAnalysisProjectDir} && " . $commandPl . " -r {$r} -s {$s} -b {$childPath}{$m} -f {$fatherPath} 2>log";
                Log::info('执行命令：' . $command);
                // 执行shell命令
                exec($command, $output, $returnVar);

                if ($returnVar === 0) {
                    Log::info("找到以下文件:");
                    
                    // 符合条件-更新检测结果状态为成功
                    $family->report_result = Family::REPORT_RESULT_SUCCESS;
                    $family->report_time = date('Y-m-d');
                    $family->save();
                } else {
                    Log::info("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $family->report_result = family::REPORT_RESULT_FAIL;
                    $family->save();
                }
                Log::info('家系分析完成-' . date('Y-m-d H:i:s'));
            }
            return;
        } catch (\Exception $e) {
            Log::error('家系分析出错：' . $e->getMessage());
        }
    }
}
