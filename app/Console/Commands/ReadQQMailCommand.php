<?php

namespace App\Console\Commands;

use App\Jobs\DownloadSampleJob;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class ReadQQMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:readqq';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read emails from QQ mailbox';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 配置IMAP客户端
        $client = Client::account('qq');
        $client->connect();

        // 获取收件箱
        $folder = $client->getFolder('INBOX');

        // 获取所有邮件
        $messages = $folder->messages()->since(now()->subDays(1))->get();

        if ($messages->count() > 0) {
            // 遍历邮件
            foreach ($messages as $message) {
                // 输出邮件信息
                // $this->info("Subject: " . $message->getSubject());
                // $this->info("From: " . $message->getFrom()[0]->mail);
                // $this->info("Date: " . $message->getDate());
                // $this->info("Message: " . $message->getTextBody());
                $this->info("----------------------------------------");

                $textBody = $message->getTextBody();
                // 使用正则表达式匹配 oss:// 路径
                preg_match_all('/oss:\/\/[^\s]+/', $textBody, $matches);
                // 输出匹配到的 oss:// 路径
                foreach ($matches[0] as $match) {
                    $match = escapeshellarg($match);
                    $this->info($match);
                    // 异步调用 php artisan sample:download $match
                    DownloadSampleJob::dispatch($match)->onQueue('downloads');
                    $this->info("异步调用成功");
                }
            }
        } else {
            $this->error('No emails found.');
        }

        return 0;
    }
}