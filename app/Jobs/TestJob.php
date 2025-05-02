<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
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
        Log::info("测试job开始：{$this->id}-".date('Y-m-d H:i:s'));
        $this->runAnalysis($this->id);
        Log::info("测试job结束：{$this->id}-".date('Y-m-d H:i:s'));
    }

    protected function runAnalysis($id): void
    {
        try {
                $command = "ls /var/www/paternity";
                Log::info('测试job-执行命令：'.$command);
                // 执行shell命令
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    foreach ($output as $file) {
                        Log::info('测试job-找到文件：'.$file);
                    }
                } else {
                    Log::error("测试job-未找到文件或命令执行失败");
                }
                Log::info('测试job完成-'.date('Y-m-d H:i:s'));
        } catch (\Exception $e) {
            Log::error('测试job-出错：'.$e->getMessage());
        }
    }
}
