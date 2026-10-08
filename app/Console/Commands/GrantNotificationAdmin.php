<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantNotificationAdmin extends Command
{
    protected $signature = 'notifications:grant-admin {email}';

    protected $description = 'Grant an existing user access to the notification console';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('用户不存在');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => true])->save();
        $this->info('已授予消息平台管理员权限');

        return self::SUCCESS;
    }
}
