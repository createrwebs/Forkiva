<?php

namespace Modules\Pos\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Events\OpenPosSession;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\User\Models\User;
use Tests\TestCase;

class PosActiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_session_is_available_without_a_last_session_pointer_or_cache_flush(): void
    {
        [$branch, $register, $user] = $this->createRegister();
        $session = $this->createSession($branch, $register, $user);

        Cache::tags('pos_registers')->forever(
            makeCacheKey(['pos_registers', "branch-$branch->id", 'with_last_session', 'list']),
            collect([['id' => $register->id, 'name' => $register->name]])
        );

        $this->assertNull($register->fresh()->last_session_id);
        $this->assertSame($session->id, PosRegister::list($branch->id, true)->first()['session']['id']);
        $this->assertSame($session->id, PosRegister::activeSession($register->id)?->id);
        $this->assertSame(
            $session->id,
            app(PosCashMovementServiceInterface::class)->getPosActiveSession($register->id)?->id
        );
    }

    public function test_closed_historical_pointer_does_not_hide_an_open_session(): void
    {
        [$branch, $register, $user] = $this->createRegister();
        $closed = $this->createSession($branch, $register, $user, PosSessionStatus::Closed);
        $register->lastSession()->associate($closed);
        $register->save();
        $open = $this->createSession($branch, $register, $user);

        $this->assertSame($open->id, PosRegister::list($branch->id, true)->first()['session']['id']);
        $this->assertSame($open->id, PosRegister::activeSession($register->id)?->id);
    }

    public function test_register_list_tracks_open_close_and_reopen_without_register_updates(): void
    {
        Event::fake([OpenPosSession::class, ClosePosSession::class]);
        [$branch, $register, $user] = $this->createRegister();
        $this->actingAs($user);
        $service = app(PosSessionServiceInterface::class);
        $data = ['branch_id' => $branch->id, 'pos_register_id' => $register->id];

        $this->assertArrayNotHasKey('session', PosRegister::list($branch->id, true)->first());
        $open = $service->open($data);
        $this->assertSame($open->id, PosRegister::list($branch->id, true)->first()['session']['id']);

        try {
            $service->open($data);
            $this->fail('A register cannot open another session while one is already open.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                [__('pos::messages.session_already_opened')],
                $exception->errors()['pos_register_id']
            );
        }

        $service->close($open->id, ['declared_cash' => 0]);
        $this->assertArrayNotHasKey('session', PosRegister::list($branch->id, true)->first());
        $this->assertNull(PosRegister::activeSession($register->id));

        $reopened = $service->open($data);
        $this->assertNotSame($open->id, $reopened->id);
        $this->assertSame($reopened->id, PosRegister::list($branch->id, true)->first()['session']['id']);
        $this->assertSame($reopened->id, PosRegister::activeSession($register->id)?->id);
    }

    public function test_deleted_sessions_are_not_available_and_branches_remain_scoped(): void
    {
        [$branch, $register, $user] = $this->createRegister();
        [$otherBranch, $otherRegister, $otherUser] = $this->createRegister();
        $session = $this->createSession($branch, $register, $user);
        $otherSession = $this->createSession($otherBranch, $otherRegister, $otherUser);

        $this->assertSame([$register->id], PosRegister::list($branch->id, true)->pluck('id')->all());
        $session->delete();

        $this->assertArrayNotHasKey('session', PosRegister::list($branch->id, true)->first());
        $this->assertNull(PosRegister::activeSession($register->id));
        $this->assertSame($otherSession->id, PosRegister::activeSession($otherRegister->id)?->id);
        $this->assertArrayNotHasKey('session', PosRegister::list($otherBranch->id)->first());
    }

    /** @return array{Branch, PosRegister, User} */
    private function createRegister(): array
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

        return [$branch, $register, $user];
    }

    private function createSession(Branch $branch, PosRegister $register, User $user, PosSessionStatus $status = PosSessionStatus::Open): PosSession
    {
        return PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'status' => $status,
        ]);
    }
}
