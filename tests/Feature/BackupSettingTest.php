<?php

namespace Tests\Feature;

use App\Models\BackupSetting;
use App\Support\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B4 — concorrência em BackupSetting::current() (chave de escopo não nula + UNIQUE).
 */
class BackupSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_the_same_single_configuration_per_scope(): void
    {
        Tenant::set(null);

        $first = BackupSetting::current();
        $second = BackupSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('platform', $first->scope_key);
        $this->assertSame(1, BackupSetting::where('scope_key', 'platform')->count());
    }

    public function test_unique_constraint_prevents_duplicate_scope_configuration(): void
    {
        BackupSetting::create(['scope_key' => 'platform', 'institution_id' => null]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Simula a corrida concorrente: o segundo INSERT deve ser rejeitado pelo banco.
        BackupSetting::create(['scope_key' => 'platform', 'institution_id' => null]);
    }

    public function test_current_is_isolated_per_institution(): void
    {
        $a = $this->makeInstitution();
        $b = $this->makeInstitution();

        Tenant::set($a);
        $settingA = BackupSetting::current();

        Tenant::set($b);
        $settingB = BackupSetting::current();

        $this->assertNotSame($settingA->id, $settingB->id);
        $this->assertSame('institution:'.$a->id, $settingA->scope_key);
        $this->assertSame('institution:'.$b->id, $settingB->scope_key);
    }
}
