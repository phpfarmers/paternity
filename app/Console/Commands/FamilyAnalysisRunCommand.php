<?php

namespace App\Console\Commands;

use App\Models\Family;
use Illuminate\Console\Command;
use App\Models\Sample;

class FamilyAnalysisRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'family:analysis:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动分析家系';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /* $families = Family::with(['samples'])->where('report_result', Family::REPORT_RESULT_UNKNOWN)
        ->whereHas('samples', function ($query) {
            $query->where('analysis_result', Sample::ANALYSIS_RESULT_SUCCESS);
        })
        ->whereDoesntHave('samples', function ($query) {
            $query->where('analysis_result', '!=', Sample::ANALYSIS_RESULT_SUCCESS);
        })
        ->limit(1)->get(); */
        $families = Family::where('report_result', Family::REPORT_RESULT_UNKNOWN)
        ->orderBy('report_times', 'asc')
        ->orderBy('id', 'asc')
        ->limit(1)->get();
        
        echo "<pre>";
        print_r($families->toArray());
        exit;
        if ($families->isEmpty()) {
            $this->info('没有要分析的家系');
            return 0;
        }
        // 获取样本信息
        $samples = Sample::leftJoin('families_samples', 'families_samples.sample_id', '=', 'samples.id')
        ->whereIn('families_samples.family_id', $families->pluck('id')->toArray())
        ->where('analysis_result', Sample::ANALYSIS_RESULT_SUCCESS)
        ->get()->toArray();
        // 将样本信息用家系id分组
        $samplesGroupByFamilyId = [];
        foreach ($samples as $sample) {
            $samplesGroupByFamilyId[$sample['family_id']][$sample->sample_type] = $sample;
        }

        try {
            foreach ($families as $family) {
                $this->info('家系分析开始：'.$family->id).'-'.date('Y-m-d H:i:s');
                // 家系分析变为分析中
                $family->report_result = Family::REPORT_RESULT_ANALYZING;
                $family->report_times  += 1;
                $family->save();
                if(!isset($samplesGroupByFamilyId[$family->id]) || count($samplesGroupByFamilyId[$family->id]) !=3){
                    $this->info('家系分析：'.$family->name.'，样本分析完成数量不正确');
                    continue;
                }
                // TODO:脚本
                // shell命令参数
                // cd {分析目录} && /path/script/parse_perbase.pl -r 2 -s 0.003 -n All.baseline.tsv -b /path/{胎儿编号}.base.txt -m /path/{母本编号}.base.txt -f /path/{父本编号}.base.txt -o {输出前缀名，可以是胎儿编号} && Rscript /path/script/cal.r --args {输出前缀名，可以是胎儿编号}.result.tsv > {输出前缀名，可以是胎儿编号}.summary
                // 分析目录-待沟通确定?
                $analysisDir = escapeshellarg('/QinZiProject');
                // 胎儿编号
                $childSample = $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_CHILD]['sample_name'];
                $childPath = escapeshellarg($childSample.'.base.txt');
                // 母本编号
                $motherSample = $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_MOTHER]['sample_name'];
                $motherPath = escapeshellarg($motherSample.'.base.txt');
                // 父本编号
                $fatherSample = $samplesGroupByFamilyId[$family->id][Sample::SAMPLE_TYPE_FATHER]['sample_name'];
                $fatherPath = escapeshellarg($fatherSample.'.base.txt');
                $childTsv = escapeshellarg($childSample.'.result.tsv');
                $childSummary = escapeshellarg($childSample.'.summary');
                // shell命令参数
                // $command = "cd /QinZiProject && ~/scripts/parse_perbase.pl -r 4 -s 0.008 -n /share/guoyuntao/workspace/QinZi_20241125/wbc_baseline_noumi_20250225/All.baseline.tsv -b AKT103-S.1G/AKT103-S.1G.base.txt -m parent_bases/AKT103-M.base.txt -f parent_bases/AKT103-F.base.txt -o AKT103-S.1G.xxx 2>log && Rscript /path/script/cal.r --args AKT103-S.1G.xxx.result.tsv > AKT103-S.1G.xxx.summary";
                $command = "cd {$analysisDir} && ~/scripts/parse_perbase.pl -r 4 -s 0.008 -n All.baseline.tsv -b {$childPath} -m {$motherPath} -f {$fatherPath} -o {$childSample} 2>log && Rscript /path/script/cal.r --args {$childTsv} > {$childSummary}";
                // 执行shell命令
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    $this->info("找到以下文件:");
                    foreach ($output as $file) {
                        $this->info($file);
                        // 获取文件名
                        $fileName = basename($file);
                        $this->info($fileName);
                    }
                    // 符合条件-更新检测结果状态为成功
                    if(count($output) > 0){
                        $family->report_result = Family::REPORT_RESULT_SUCCESS;
                        $family->report_time = date('Y-m-d');
                        $family->save();
                    }else{
                        // 未下机-变为未检测-继续检测
                        $this->info("文件数量不正确");
                        $family->report_result = Family::REPORT_RESULT_UNKNOWN;
                        $family->save();
                    }
                } else {
                    $this->error("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $family->report_result = family::REPORT_RESULT_FAIL;
                    $family->save();
                }
                $this->info('家系分析完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('家系分析出错：'.$e->getMessage());
        }
        return 0;
    }
}