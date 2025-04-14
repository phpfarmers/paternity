<?php

namespace App\Console\Commands;

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
        $messages = $folder->messages()->all()->get();

        if ($messages->count() > 0) {
            // 遍历邮件
            foreach ($messages as $message) {
                // 输出邮件信息
                $this->info("Subject: " . $message->getSubject());
                $this->info("From: " . $message->getFrom()[0]->mail);
                $this->info("Date: " . $message->getDate());
                $this->info("Message: " . $message->getTextBody());
                $this->info("----------------------------------------");
            }
        } else {
            $this->error('No emails found.');
        }

        return 0;
    }
}