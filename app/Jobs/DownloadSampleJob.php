<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadSampleJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $ossPath;

    /**
     * Create a new job instance.
     *
     * @param string $ossPath
     */
    public function __construct($ossPath)
    {
        $this->ossPath = $ossPath;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 实现下载逻辑
        // 例如：调用 shell 脚本或 PHP 函数处理下载
        Log::info("job开始下载：{$this->ossPath}-".date('Y-m-d H:i:s'));
        $ossPath = escapeshellarg($this->ossPath);
        $this->runDownload($ossPath);
        Log::info("job下载结束：{$this->ossPath}-".date('Y-m-d H:i:s'));
    }
    
    protected function runDownload($ossPath): void
    {
        $logFile = storage_path('logs/sample_download.log');
        $command = "php artisan sample:download {$ossPath} >> {$logFile} 2>&1";
        Log::info("执行命令：{$command}");
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error("样本下载失败：{$ossPath}", ['output' => $output]);
        }else{
            Log::info("样本下载成功：{$ossPath}");
        }
    }
}