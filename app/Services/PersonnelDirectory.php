<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PersonnelDirectory
{
    /**
     * @param  array<int, string>  $ids
     * @return array<string, array{email: ?string, mobile: ?string}|null>
     */
    public function lookup(array $ids): array
    {
        if (! DB::table('personnel_directory_syncs')->where('id', 1)->exists()) {
            throw new RuntimeException('人员信息尚未完成首次同步');
        }

        $people = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table('personnel_directory_entries')
                ->whereIn('xgh', $chunk)->get(['xgh', 'dzyx', 'yddh']);

            foreach ($rows as $row) {
                $id = (string) $row->xgh;
                if (array_key_exists($id, $people)) {
                    $people[$id] = null;

                    continue;
                }

                $people[$id] = [
                    'email' => $row->dzyx === null ? null : trim((string) $row->dzyx),
                    'mobile' => $row->yddh === null ? null : trim((string) $row->yddh),
                ];
            }
        }

        return $people;
    }
}
