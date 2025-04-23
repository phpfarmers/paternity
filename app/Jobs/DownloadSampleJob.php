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
        Log::info("开始下载：{$this->ossPath}");
        exec("php artisan sample:download {$this->ossPath} > /dev/null 2>&1");
        Log::info("下载结束：{$this->ossPath}");
    }
}