<?php

namespace App\Console\Commands;

use App\Services\PersonnelSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncPersonnelDirectory extends Command
{
    protected $signature = 'personnel:sync';

    protected $description = '将中间库人员联系方式同步到本地快照';

    public function handle(PersonnelSynchronizer $synchronizer): int
    {
        try {
            $count = $synchronizer->sync();
            $this->info("人员信息同步完成：{$count} 条");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('人员信息同步失败，已保留上次成功的本地快照：'.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
