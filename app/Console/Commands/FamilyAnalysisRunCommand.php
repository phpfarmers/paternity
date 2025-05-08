<?php

namespace App\Console\Commands;

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
        $this->info('家系分析开始');
        Log::info('家系分析开始');
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
            $this->info('没有要分析的家系');
            Log::info('没有要分析的家系');
            return 0;
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
                $this->error('样本未分析成功，请先分析样本:' . $sample['sample_name']);
                $canAnalysis = false;
                break;
            }
        }
        if (!$canAnalysis) {
            $this->error('存在未分析成功的样本，请先分析后再执行分析');
            Log::info('存在未分析成功的样本，请先分析后再执行分析');
            return;
        }

        try {
            foreach ($families as $family) {
                $this->info('家系分析开始：' . $family->id . '-' . date('Y-m-d H:i:s'));
                Log::info('家系分析开始：' . $family->id . '-' . date('Y-m-d H:i:s'));
                // 家系分析变为分析中
                $family->report_result = Family::REPORT_RESULT_ANALYZING;
                $family->report_times  += 1;
                $family->save();
                if (!isset($samplesGroupByFamilyId[$family->id]) || count($samplesGroupByFamilyId[$family->id]) < 2) {
                    $this->info('家系分析：' . $family->name . '，样本分析完成数量不正确');
                    Log::info('家系分析：' . $family->name . '，样本分析完成数量不正确');
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
                $this->info('执行命令：' . $command);
                Log::info('执行命令：' . $command);
                // 执行shell命令
                exec($command, $output, $returnVar);

                if ($returnVar === 0) {
                    $this->info("找到以下文件:");
                    Log::info("找到以下文件:");
                    foreach ($output as $file) {
                        $this->info($file);
                        // 获取文件名
                        $fileName = basename($file);
                        $this->info($fileName);
                    }
                    // 符合条件-更新检测结果状态为成功
                    $family->report_result = Family::REPORT_RESULT_SUCCESS;
                    $family->report_time = date('Y-m-d');
                    $family->save();
                } else {
                    $this->error("未找到文件或命令执行失败");
                    Log::info("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $family->report_result = family::REPORT_RESULT_FAIL;
                    $family->save();
                }
                $this->info('家系分析完成-' . date('Y-m-d H:i:s'));
                Log::info('家系分析完成-' . date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('家系分析出错：' . $e->getMessage());
            Log::info('家系分析出错：' . $e->getMessage());
        }
        return 0;
    }
}
