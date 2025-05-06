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

    // 超时时间 默认1小时
    protected $timeout = 3600;
    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始下载样本下机文件'.date('Y-m-d H:i:s'));
        $data = config('data');
        $ossPath = $this->argument('oss_path') ?? ''; // 指定要下载的远程目录-优先取参数，其次取配置文件
        
        // 默认取全量数据
        $ossDataLocal = $data['oss_data_local'] ?? '/akdata/oss_data/'; // oss数据目录 样本数据下机目录
        $ossDataRemote = $data['oss_data_remote'] ?? 'oss://ak2024-2446/'; // 要下载的远程目录
        if(!empty($ossPath)) {
            $ossDataRemoteArr = explode('/', $ossDataRemote);
            $ossDataRemoteCount = count($ossDataRemoteArr);

            $ossDataRemote = $ossPath; // 远程目录
            // 处理本地目录，放到oss_data_local 目录下
            $ossPathArr = explode('/', $ossPath, $ossDataRemoteCount); // 分割路径
            
            $ossSecondPath = $ossPathArr[$ossDataRemoteCount-1] ?? ''; // 第二段路径
            
            $ossDataLocal  = $ossDataLocal.$ossSecondPath.'/'; // 本地目录
        }
        
        $ossDataRemote = escapeshellarg($ossDataRemote); // 转义
        $ossDataLocal = escapeshellarg($ossDataLocal); // 转义
        Log::info('开始下载样本下机文件'.$ossDataRemote.date('Y-m-d H:i:s'));
        Log::info('本地目录：'.$ossDataLocal);
        Log::info('远程目录：'.$ossDataRemote);
        // 免密：sudo 增加：需要在服务器上执行命令：sudo visudo,在文件末尾添加：labserver2 ALL=(root) NOPASSWD: /bin/ossutil
        // $command = "sudo -u labserver2 ossutil cp -r -u -c /akdata/software/oss-browser-linux-x64/conf {$ossDataRemote} {$ossDataLocal} 2>&1"; // 下载命令
        $command = "ossutil cp -r -u -c /akdata/software/oss-browser-linux-x64/conf {$ossDataRemote} {$ossDataLocal} 2>&1"; // 下载命令
        Log::info('执行命令：'.$command);
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