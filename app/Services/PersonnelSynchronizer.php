<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PersonnelSynchronizer
{
    public function sync(): int
    {
        $rows = DB::connection('personnel')->table('t_cx_zzqxryxx')
            ->get(['xgh', 'dzyx', 'yddh']);

        if ($rows->isEmpty()) {
            throw new RuntimeException('人员信息源表为空，已保留本地上次同步结果');
        }

        $entries = [];
        foreach ($rows as $row) {
            $id = trim((string) $row->xgh);
            if ($id === '' || array_key_exists($id, $entries)) {
                throw new RuntimeException('人员信息源表存在空学工号或重复学工号，已保留本地上次同步结果');
            }

            $entries[$id] = [
                'xgh' => $id,
                'dzyx' => $row->dzyx === null ? null : trim((string) $row->dzyx),
                'yddh' => $row->yddh === null ? null : trim((string) $row->yddh),
            ];
        }

        DB::transaction(function () use ($entries): void {
            DB::table('personnel_directory_entries')->delete();
            foreach (array_chunk(array_values($entries), 500) as $chunk) {
                DB::table('personnel_directory_entries')->insert($chunk);
            }
            DB::table('personnel_directory_syncs')->updateOrInsert(
                ['id' => 1],
                ['row_count' => count($entries), 'synced_at' => now()],
            );
        });

        return count($entries);
    }
}
