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
            $samplesGroupByFamilyId[$sample['family_id']][] = $sample;
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
                $sampleName = $sample->sample_name;
                $r1Url = $sample->r1_url;
                $r2Url = $sample->r2_url;
                $analysisProcess = $sample->analysis_process;
                $outputDir = escapeshellarg('/var/www/paternity/output/'); // 输出路径
                $command = "/path/cript/run_qinzi.pl \ -s {$sampleName} \ -r1 {$r1Url} \ -r2 {$r2Url} \ -u {$analysisProcess} \ -o {$outputDir} ";
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
                        $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
                        $sample->analysis_time = date('Y-m-d');
                        $sample->save();
                    }else{
                        // 未下机-变为未检测-继续检测
                        $this->info("文件数量不正确");
                        $sample->analysis_result = Sample::ANALYSIS_RESULT_UNKNOWN;
                        $sample->save();
                    }
                } else {
                    $this->error("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                    $sample->save();
                }
                $this->info('家系分析完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('家系分析出错：'.$e->getMessage());
        }
        return 0;
    }
}