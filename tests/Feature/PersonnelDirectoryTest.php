<?php

namespace Tests\Feature;

use App\Services\PersonnelDirectory;
use App\Services\PersonnelSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PersonnelDirectoryTest extends TestCase
{
    private function sourceTable(): void
    {
        config()->set('database.connections.personnel', [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]);
        Schema::connection('personnel')->create('t_cx_zzqxryxx', function (Blueprint $table) {
            $table->string('xgh');
            $table->string('dzyx')->nullable();
            $table->string('yddh')->nullable();
        });
    }

    public function test_daily_sync_refreshes_local_snapshot_and_lookup_never_reads_source(): void
    {
        $this->sourceTable();
        DB::connection('personnel')->table('t_cx_zzqxryxx')->insert([
            ['xgh' => '001', 'dzyx' => ' first@example.edu.cn ', 'yddh' => ' 13800138000 '],
            ['xgh' => '002', 'dzyx' => null, 'yddh' => null],
            ['xgh' => '003', 'dzyx' => 'unused@example.edu.cn', 'yddh' => null],
        ]);

        $this->assertSame(3, app(PersonnelSynchronizer::class)->sync());
        $this->assertSame(3, DB::table('personnel_directory_syncs')->value('row_count'));

        DB::connection('personnel')->table('t_cx_zzqxryxx')->delete();

        $people = app(PersonnelDirectory::class)->lookup(['001', '002', 'missing']);

        $this->assertSame(['email' => 'first@example.edu.cn', 'mobile' => '13800138000'], $people['001']);
        $this->assertSame(['email' => null, 'mobile' => null], $people['002']);
        $this->assertArrayNotHasKey('003', $people);
        $this->assertArrayNotHasKey('missing', $people);

        DB::connection('personnel')->table('t_cx_zzqxryxx')->insert([
            'xgh' => '004', 'dzyx' => 'new@example.edu.cn', 'yddh' => null,
        ]);
        $this->assertSame(1, app(PersonnelSynchronizer::class)->sync());
        $this->assertArrayNotHasKey('001', app(PersonnelDirectory::class)->lookup(['001', '004']));
        $this->assertSame('new@example.edu.cn', app(PersonnelDirectory::class)->lookup(['004'])['004']['email']);
    }

    public function test_empty_source_does_not_replace_last_good_snapshot(): void
    {
        $this->sourceTable();
        DB::connection('personnel')->table('t_cx_zzqxryxx')->insert([
            'xgh' => '001', 'dzyx' => 'first@example.edu.cn', 'yddh' => null,
        ]);
        app(PersonnelSynchronizer::class)->sync();
        DB::connection('personnel')->table('t_cx_zzqxryxx')->delete();

        try {
            app(PersonnelSynchronizer::class)->sync();
            $this->fail('空源表应中止同步');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('源表为空', $exception->getMessage());
        }

        $this->assertSame('first@example.edu.cn', app(PersonnelDirectory::class)->lookup(['001'])['001']['email']);
        $this->assertSame(1, DB::table('personnel_directory_syncs')->value('row_count'));
    }

    public function test_lookup_requires_an_initial_sync(): void
    {
        $this->expectException(RuntimeException::class);

        app(PersonnelDirectory::class)->lookup(['001']);
    }
}
