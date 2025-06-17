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
        $hasUmi = false;//样本含有
        foreach ($samples as $sample) {
            $samplesGroupByFamilyId[$sample['family_id']][$sample['sample_type']] = $sample;
            if(!empty($sample['analysis_process'])){
                $hasUmi = true;
            }
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
                // 有未完成分析的样本，跳过此家系分析
                $canAnalysis = true;
                foreach ($samplesGroupByFamilyId[$family->id] as $sample) {
                    if ($sample['analysis_result'] != Sample::ANALYSIS_RESULT_SUCCESS) {
                        Log::info('家系分析家系：' . $family->name . '，样本：' . $sample['sample_name'] . '-未分析');
                        $canAnalysis = false;
                        break;
                    }
                }
                if (!$canAnalysis) {
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
                $defaultR = $hasUmi ? config('data')['family_analysis_run_command_umi_default_r'] : config('data')['family_analysis_run_command_default_r'];
                $defaultS = $hasUmi ? config('data')['family_analysis_run_command_umi_default_s'] : config('data')['family_analysis_run_command_default_s'];
                $r = !empty($family->r) ? $family->r : $defaultR; // 默认r值
                $s = !empty($family->s) ? $family->s : $defaultS; // 默认s值
                $command = "cd {$secondAnalysisProjectDir} && " . $commandPl . " -r {$r} -s {$s} -b {$childPath}{$m} -f {$fatherPath} 2>log";
                Log::info('执行命令：' . $command);
                // 执行shell命令
                putenv(config('data')['perl_path']);
                putenv(config('data')['perl_perl5ltb']);
                exec($command, $output, $returnVar);

                if ($returnVar === 0) {
                    Log::info("读取家系分析结果文件中的:");
                    // 检测简单报告文件是否存在
                    $summaryPath = config('data')['second_analysis_project'] . $fatherSample . '_vs_' . $childSample . '.result.summary.tsv';
                    if (file_exists($summaryPath)) {
                        Log::info("找到文件: " . $summaryPath);
                        // 将数组转换为集合
                        $rows = array_map('str_getcsv', file($summaryPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), array_fill(0, count(file($summaryPath)), "\t"));
                        if (!empty($rows) && isset($rows[1])) {
                            // 判断是否是真父：CPI >= 10000 为真父, 否则为假父
                            $header = array_shift($rows);
                            // 获取CPI列索引
                            $cpiIndex = array_search('CPI', $header);
                            if ($cpiIndex !== false) {
                                $cpi = $rows[1][$cpiIndex];
                                if ($cpi >= 10000) {
                                    Log::info("家系分析结果：真父");
                                    $family->is_father = Family::IS_FATHER_YES;
                                } else {
                                    Log::info("家系分析结果：假父");
                                    $family->is_father = Family::IS_FATHER_NO;
                                }
                            }
                        }
                    } else {
                        Log::info("未找到文件: " . $summaryPath);
                    }
                    // 符合条件-更新检测结果状态为成功
                    $family->report_result = Family::REPORT_RESULT_SUCCESS;
                    $family->r = $r;
                    $family->s = $s;
                    $family->report_time = date('Y-m-d H:i:s');
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
