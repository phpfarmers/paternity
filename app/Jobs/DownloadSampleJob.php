<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadSampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // oss路径
    protected $ossPath;
    // 重试次数
    public $tries = 3;
    public $timeout = 3000;


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
        Log::info("job开始下载：{$this->ossPath}-" . date('Y-m-d H:i:s'));
        $this->runDownload($this->ossPath);
        Log::info("job下载结束：{$this->ossPath}-" . date('Y-m-d H:i:s'));
    }

    protected function runDownload($ossPath): void
    {
        /* $logFile = storage_path('logs/sample_download.log');
        $command = "php artisan sample:download {$ossPath} >> {$logFile} 2>&1";
        Log::info("执行命令：{$command}");
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error("样本下载失败：{$ossPath}", ['output' => $output]);
        }else{
            Log::info("样本下载成功：{$ossPath}");
        } */

        // 默认取全量数据
        $ossDataLocal = $data['oss_data_local'] ?? '/akdata/oss_data/'; // oss数据目录 样本数据下机目录
        $ossDataRemote = $data['oss_data_remote'] ?? 'oss://ak2024-2446/'; // 要下载的远程目录
        if (empty($ossPath)) {
            return;
        }
        $ossDataRemoteArr = explode('/', $ossDataRemote);
        $ossDataRemoteCount = count($ossDataRemoteArr);

        $ossDataRemote = $ossPath; // 远程目录
        // 处理本地目录，放到oss_data_local 目录下
        $ossPathArr = explode('/', $ossPath, $ossDataRemoteCount); // 分割路径

        $ossSecondPath = $ossPathArr[$ossDataRemoteCount - 1] ?? ''; // 第二段路径

        $ossDataLocal  = $ossDataLocal . $ossSecondPath . '/'; // 本地目录

        $ossDataRemote = escapeshellarg($ossDataRemote); // 转义
        $ossDataLocal = escapeshellarg($ossDataLocal); // 转义
        Log::info('开始下载样本下机文件' . $ossDataRemote . date('Y-m-d H:i:s'));
        Log::info('本地目录：' . $ossDataLocal);
        Log::info('远程目录：' . $ossDataRemote);
        // 免密：sudo 增加：需要在服务器上执行命令：sudo visudo,在文件末尾添加：labserver2 ALL=(root) NOPASSWD: /bin/ossutil
        // $command = "sudo -u labserver2 ossutil cp -r -u -c /akdata/software/oss-browser-linux-x64/conf {$ossDataRemote} {$ossDataLocal} 2>&1"; // 下载命令
        $command = "ossutil cp -r -u -c /akdata/software/oss-browser-linux-x64/conf {$ossDataRemote} {$ossDataLocal} 2>&1"; // 下载命令
        Log::info('执行命令：' . $command);
        exec($command, $output, $returnVar);
        if ($returnVar === 0) {
            Log::info("下载成功");
        } else {
            Log::error("下载失败");
        }
        Log::info('下载样本下机文件结束' . date('Y-m-d H:i:s'));
    }
}
