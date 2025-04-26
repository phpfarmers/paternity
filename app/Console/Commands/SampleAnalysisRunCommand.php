<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sample;
use Illuminate\Support\Facades\Log;

class SampleAnalysisRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample:analysis:run {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动分析样本';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 设定开始位置标识
        start:
        $id = $this->argument('id') ?? 0;
        $this->info('开始分析样本');
        Log::info('开始分析样本');
        $samples = Sample::where('check_result', Sample::CHECK_RESULT_SUCCESS);
        // 指定id-前端操作-只要未完成的都可操作
        if ($id > 0) {
            $samples = $samples->where('id', $id)->where('analysis_result', '!=', Sample::ANALYSIS_RESULT_UNKNOWN);
        } else {
            $samples = $samples->where('analysis_result', Sample::ANALYSIS_RESULT_UNKNOWN);
        }
        $samples = $samples->orderBy('analysis_times', 'asc')->orderBy('id', 'asc')->limit(1)->get();
        if ($samples->isEmpty()) {
            $this->info('没有要分析的样本');
            Log::info('没有要分析的样本');
            return 0;
        }
        // 如果正在分析中的样本数量大50，停止1分钟，再从start位置开始执行
        $analyzingCount = Sample::where('analysis_result', Sample::ANALYSIS_RESULT_ANALYZING)->count();
        if ($analyzingCount > 50) {
            $this->info('正在分析中的样本数量大于50，停止1分钟');
            Log::info('正在分析中的样本数量大于50，停止1分钟');
            sleep(60);
            goto start;
        }
        try {
            foreach ($samples as $sample) {
                $this->info('样本分析开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                Log::info('样本分析开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                // 样本分析变为分析中
                $sample->analysis_result = Sample::ANALYSIS_RESULT_ANALYZING;
                $sample->analysis_times += 1;
                $sample->save();
                
                // shell命令参数
                $sampleName = escapeshellarg($sample->sample_name);
                $r1Url = escapeshellarg($sample->r1_url);
                $r2Url = escapeshellarg($sample->r2_url);
                $analysisProcess = escapeshellarg($sample->analysis_process);

                $ossAnalysisProjectLocal = config('data')['analysis_project']; // 本地样本分析目录
                $outputDir = 'pipeline_'.$sample->sample_name.'_run_'.date('YmdHis', time()); // 输出路径
                $outputFullDir = escapeshellarg($ossAnalysisProjectLocal.$outputDir); // 输出路径
                
                $commandPl = config('data')['sample_analysis_run_command_pl'];
                $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url} -u {$analysisProcess} -o {$outputFullDir} 2>&1";
                // $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url} -u {$analysisProcess} 2>&1";
                $this->info('执行命令：'.$command);
                Log::info('执行命令：'.$command);
                // 执行shell命令
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    $this->info("找到以下文件:");
                    Log::info("找到以下文件:");
                    foreach ($output as $file) {
                        // $this->info($file);
                        // 获取文件名
                        $fileName = basename($file);
                        $this->info($fileName);
                    }
                    // 符合条件-更新检测结果状态为成功
                    // if(count($output) > 0){
                        $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
                        $sample->analysis_time = date('Y-m-d');
                        $sample->output_dir = $outputDir;
                        $sample->save();
                    // }else{
                    //     // 未下机-变为未检测-继续检测
                    //     $this->info("文件数量不正确");
                    //     $sample->analysis_result = Sample::ANALYSIS_RESULT_UNKNOWN;
                    //     $sample->save();
                    // }
                } else {
                    $this->error("未找到文件或命令执行失败");
                    Log::info("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                    $sample->output_dir = $outputDir;
                    $sample->save();
                }
                $this->info('样本分析完成-'.date('Y-m-d H:i:s'));
                Log::info('样本分析完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('样本分析出错：'.$e->getMessage());
            Log::info('样本分析出错：'.$e->getMessage());
        }
        return 0;
    }
}