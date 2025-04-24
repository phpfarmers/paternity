<?php

namespace App\Jobs;

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
        Log::info("开始家系分析：{$this->id}");
        exec("php artisan family:analysis:run {$this->id} > /dev/null 2>&1");
        Log::info("家系分析结束：{$this->id}");
    }
}
