<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $date = date('Y-m-d');
        // 每一分钟执行一次 家系分析
        $schedule->command('family:analysis:run')
        ->everyMinute()  // 每分钟执行一次
        ->appendOutputTo(storage_path('logs/family_analysis_run_cron'.$date.'.log'));
        // 读取163邮件
        $schedule->command('mail:read163')
        ->everyMinute()  // 每分钟执行一次
        ->appendOutputTo(storage_path('logs/mail_read163_cron'.$date.'.log'));
        // 每2分钟执行一次 样本分析
        $schedule->command('sample:analysis:run')
        ->cron('*/2 * * * *')  // 每2分钟执行一次
        ->appendOutputTo(storage_path('logs/sample_analysis_run_cron'.$date.'.log'));
        // 每1分钟执行一次 样本检测
        $schedule->command('sample:check')
        ->everyMinute()
        ->appendOutputTo(storage_path('logs/sample_check_cron'.$date.'.log'));
        // 第1小时执行全量下载
        $schedule->command('sample:download')
        ->cron('0 * * * *')
        ->appendOutputTo(storage_path('logs/sample_download_cron'.$date.'.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
