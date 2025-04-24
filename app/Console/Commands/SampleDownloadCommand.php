<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SampleDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample:download {oss_path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动下载样本下机文件';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始下载样本下机文件'.date('Y-m-d H:i:s'));
        $data = config('data');
        $ossDataLocal = $data['oss_data_local'] ?? '/akdata/oss_data/'; // oss数据目录 样本数据下机目录
        $ossPath = $this->argument('oss_path') ?? ''; // 要下载的远程目录-优先取参数
        $ossDataRemote = !empty($ossPath) ? $ossPath : $data['oss_data_remote'] ?? 'oss://ak2024-2446/'; // 要下载的远程目录
        
        $ossDataRemote = escapeshellarg($ossDataRemote); // 转义
        $ossDataLocal = escapeshellarg($ossDataLocal); // 转义
        Log::info('开始下载样本下机文件'.$ossDataRemote.date('Y-m-d H:i:s'));
        Log::info('本地目录：'.$ossDataLocal);
        Log::info('远程目录：'.$ossDataRemote);
        $command = "ossutil cp -r -u -c /akdata/software/oss-browser-linux-x64/conf {$ossDataRemote} {$ossDataLocal} 2>&1"; // 下载命令
        exec($command, $output, $returnVar);
        if ($returnVar === 0) {
            $this->info("下载成功");
            Log::info("下载成功");
        } else {
            $this->error("下载失败");
            Log::error("下载失败");
        }
        $this->info('下载样本下机文件结束'.date('Y-m-d H:i:s'));
        Log::info('下载样本下机文件结束'.date('Y-m-d H:i:s'));

        return 0;
    }
}