<?php

namespace App\Jobs;

use App\Models\Sample;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SampleAnalysisRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    // 重试次数
    public $tries = 3;
    public $timeout = 3000;
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
        // 实现分析逻辑
        // 例如：调用 shell 脚本或 PHP 函数处理下载
        Log::info("开始样本分析：{$this->id}-".date('Y-m-d H:i:s'));
        $this->runAnalysis($this->id);
        Log::info("样本分析结束：{$this->id}-".date('Y-m-d H:i:s'));
    }

    protected function runAnalysis($id): void
    {
        start:
        $samples = Sample::where('check_result', Sample::CHECK_RESULT_SUCCESS);
        // 指定id-前端操作-只要未完成的都可操作
        $samples = $samples->where('id', $id)->where('analysis_result', '!=', Sample::ANALYSIS_RESULT_SUCCESS);
        $samples = $samples->orderBy('analysis_times', 'asc')->orderBy('id', 'asc')->limit(1)->get();
        if ($samples->isEmpty()) {
            Log::info('没有要分析的样本');
            return;
        }
        // 如果正在分析中的样本数量大50，停止1分钟，再从start位置开始执行
        $analyzingCount = Sample::where('analysis_result', Sample::ANALYSIS_RESULT_ANALYZING)->count();
        if ($analyzingCount > 50) {
            Log::info('正在分析中的样本数量大于50，停止1分钟');
            sleep(60);
            goto start;
        }
        try {
            foreach ($samples as $sample) {
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
                $u = empty(trim($sample->analysis_process)) ? '' : ' -u '.$analysisProcess; // 默认分析流程

                $ossAnalysisProjectLocal = config('data')['analysis_project']; // 本地样本分析目录
                $outputDir = 'pipeline_'.$sample->sample_name.'_run_'.date('YmdHis', time()); // 输出路径
                $outputFullDir = escapeshellarg($ossAnalysisProjectLocal.$outputDir); // 输出路径

                $commandPl = escapeshellarg(config('data')['sample_analysis_run_command_pl']);
                $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url}{$u} -o {$outputFullDir} 2>&1";
                // $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url} -u {$analysisProcess} 2>&1";
                Log::info('执行命令：'.$command);
                // 执行shell命令
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    Log::info("找到以下文件:");
                    $sample->analysis_time = date('Y-m-d');
                    $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
                    $sample->save();
                } else {
                    Log::error("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                    $sample->output_dir = $outputDir;
                    $sample->save();
                }
                Log::info('样本分析完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            Log::error('样本分析出错：'.$e->getMessage());
        }
    }
}
