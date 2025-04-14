<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Webklex\IMAP\Facades\Client;

class Read163MailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:read163';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read emails from 163 mailbox';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 配置IMAP客户端
        $client = Client::account('default');
        $client->connect();

        // 列出所有文件夹
        /* $folders = $client->getFolders();
        foreach ($folders as $folder) {
            $this->info("Folder: " . $folder->path);
        }
        return 0; */
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