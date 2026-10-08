<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestNotificationEmail extends Command
{
    protected $signature = 'notifications:test-email {recipient}';

    protected $description = 'Send a single SMTP connectivity test email';

    public function handle(): int
    {
        $recipient = $this->argument('recipient');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('请提供有效的收件邮箱');

            return self::FAILURE;
        }

        try {
            Mail::raw('消息平台 SMTP 通道连通测试。', function ($message) use ($recipient) {
                $message->to($recipient)->subject('消息平台邮件通道测试');
            });
            $this->info('SMTP 服务器已接受测试邮件；请在收件箱确认最终到达。');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('SMTP 测试失败：'.class_basename($exception));

            return self::FAILURE;
        }
    }
}
