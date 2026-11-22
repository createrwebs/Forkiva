<?php

namespace Modules\Pos\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Pos\Database\Seeders\PosDatabaseSeeder;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\User;
use Tests\TestCase;

class PosDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_branch_users_before_pos_sessions(): void
    {
        config(['app.seed_demo_data' => true]);

        $branch = Branch::factory()->create();

        $this->assertFalse(User::query()->where('branch_id', $branch->id)->exists());

        $this->seed(PosDatabaseSeeder::class);

        $this->assertSame(2, User::query()->where('branch_id', $branch->id)->count());
        $this->assertGreaterThan(0, PosSession::query()->where('branch_id', $branch->id)->count());

        foreach (PosRegister::query()->whereBranch($branch->id)->get() as $register) {
            $sessions = PosSession::query()
                ->where('pos_register_id', $register->id)
                ->where('status', PosSessionStatus::Open)
                ->get();

            $this->assertCount(1, $sessions);
            $this->assertSame($sessions->first()->id, $register->last_session_id);
            $this->assertSame($sessions->first()->id, PosRegister::activeSession($register->id)?->id);
            $this->assertSame(
                $sessions->first()->id,
                PosRegister::list($branch->id, true)->firstWhere('id', $register->id)['session']['id']
            );
        }
    }
}
