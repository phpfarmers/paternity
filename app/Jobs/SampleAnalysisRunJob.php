<?php

namespace App\Jobs;

use App\Models\Sample;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SampleAnalysisRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    public $tries = 3;
    public $timeout = 3000;
    
    // 进程超时时间（秒）
    protected $processTimeout = 3600; // 1小时
    
    // 最大并发分析数
    protected $maxConcurrentAnalysis = 50;
    
    // 等待时间（秒）当达到最大并发时
    protected $waitTime = 60;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle(): void
    {
        Log::info("开始样本分析：{$this->id}-".date('Y-m-d H:i:s'));
        $this->runAnalysis($this->id);
        Log::info("样本分析结束：{$this->id}-".date('Y-m-d H:i:s'));
    }

    protected function runAnalysis($id): void
    {
        // 检查并发数
        while (Sample::where('analysis_result', Sample::ANALYSIS_RESULT_ANALYZING)->count() >= $this->maxConcurrentAnalysis) {
            Log::info("正在分析中的样本数量大于{$this->maxConcurrentAnalysis}，等待{$this->waitTime}秒");
            sleep($this->waitTime);
        }

        $sample = Sample::where('check_result', Sample::CHECK_RESULT_SUCCESS)
            ->where('id', $id)
            ->where('analysis_result', '!=', Sample::ANALYSIS_RESULT_SUCCESS)
            ->orderBy('analysis_times', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if (!$sample) {
            Log::info('没有要分析的样本');
            return;
        }

        try {
            Log::info('样本分析开始：'.$sample->id.'-'.date('Y-m-d H:i:s'));
            
            // 更新样本状态为分析中
            $sample->analysis_result = Sample::ANALYSIS_RESULT_ANALYZING;
            $sample->analysis_times += 1;
            $sample->save();

            // 准备参数
            $ossAnalysisProjectLocal = config('data')['analysis_project'];
            $outputDir = 'pipeline_'.$sample->sample_name.'_run_'.date('YmdHis');
            $outputFullDir = $ossAnalysisProjectLocal.$outputDir;

            // 构建命令数组（更安全的方式）
            $command = [
                config('data')['sample_analysis_run_command_pl'],
                '-s', $sample->sample_name,
                '-r1', $sample->r1_url,
                '-r2', $sample->r2_url,
                '-o', $outputFullDir
            ];

            // 如果有分析流程参数则添加
            if (!empty(trim($sample->analysis_process))) {
                array_push($command, '-u', $sample->analysis_process);
            }

            Log::info('执行命令：' . implode(' ', array_map('escapeshellarg', $command)));

            // 创建并运行进程
            $process = new Process($command);
            $process->setTimeout($this->processTimeout);
            
            try {
                $process->mustRun();
                
                Log::info("命令执行成功，输出: " . $process->getOutput());
                $sample->analysis_time = date('Y-m-d');
                $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
                $sample->output_dir = $outputDir;
                $sample->save();

            } catch (ProcessFailedException $e) {
                Log::error("命令执行失败: " . $e->getMessage());
                Log::error("错误输出: " . $process->getErrorOutput());
                
                $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                $sample->output_dir = $outputDir;
                $sample->save();
                
                // 抛出异常让队列可以重试
                throw $e;
            }

            Log::info('样本分析完成-'.date('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            Log::error('样本分析出错：'.$e->getMessage());
            throw $e; // 重新抛出异常以便队列处理
        }
    }
}
