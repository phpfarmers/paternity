<?php

namespace App\Jobs;

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
        $id = escapeshellarg($this->id);
        
        $this->runAnalysis($id);

        Log::info("样本分析结束：{$this->id}-".date('Y-m-d H:i:s'));
    }

    protected function runAnalysis($id): void
    {
        $logFile = storage_path('logs/sample_analysis.log');
        $command = "php artisan sample:analysis:run {$id} >> {$logFile} 2>&1";
        Log::info("执行命令：{$command}");
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error("样本分析失败：{$id}", ['output' => $output]);
        }else{
            Log::info("样本分析成功：{$id}");
        }
    }
}
