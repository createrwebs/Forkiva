<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Modules\Branch\Models\Branch;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\User\Models\User;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PosSessionCustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_details_identify_the_responsible_cashier_and_per_session_close_capability(): void
    {
        $session = $this->createSession();
        $owner = auth()->user();
        $this->getJson($this->url($session))->assertOk()
            ->assertJsonPath('body.responsible_cashier.id', $owner->id)
            ->assertJsonPath('body.responsible_cashier.name', $owner->name)
            ->assertJsonPath('body.can_close', true)
            ->assertJsonPath('body.closing_custody', null);
        $this->getJson('/api/v1/pos/sessions')->assertOk()->assertJsonPath('body.data.0.can_close', true);
        $other = $this->cashier($session);
        $this->actingAs($other);
        $this->getJson($this->url($session))->assertOk()->assertJsonPath('body.can_close', false);
        $this->getJson('/api/v1/pos/sessions')->assertOk()->assertJsonPath('body.data.0.can_close', false);
    }

    public function test_a_cashier_with_close_permission_cannot_review_or_close_someone_elses_session(): void
    {
        $session = $this->createSession();
        $before = $session->fresh()->getRawOriginal();
        $this->actingAs($this->cashier($session));
        $this->postJson($this->url($session).'/closing-preview')->assertForbidden();
        $this->putJson($this->url($session).'/close', ['declared_cash' => '500'])->assertForbidden();
        $this->assertSame($before, $session->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pos_cash_movements', 0);
        Event::assertNotDispatched(ClosePosSession::class);
    }

    public function test_custody_is_enforced_by_the_service_as_well_as_the_api(): void
    {
        $session = $this->createSession();
        $this->actingAs($this->cashier($session));
        $service = app(PosSessionServiceInterface::class);
        foreach ([fn () => $service->closingPreview($session->id), fn () => $service->close($session->id, ['declared_cash' => '500'])] as $action) {
            try {
                $action();
                $this->fail('Closing custody must be checked inside the service.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
    }

    public function test_delegated_closing_requires_a_reason_and_preserves_the_responsible_cashier(): void
    {
        $session = $this->createSession();
        $owner = auth()->user();
        $manager = $this->cashier($session, true);
        $this->actingAs($manager);
        $preview = $this->preview($session, '495')->assertOk()
            ->assertJsonPath('body.review.requires_closing_reason', true)
            ->assertJsonPath('body.review.requires_variance_reason', true)
            ->assertJsonPath('body.review.actor.id', $manager->id);
        $data = ['declared_cash' => '495', 'review_token' => $preview->json('body.review.token')];
        $this->putJson($this->url($session).'/close', $data)->assertUnprocessable()->assertJsonValidationErrors('closing_reason');
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $this->putJson($this->url($session).'/close', [
            ...$data,
            'closing_reason' => '  Cashier shift finished; drawer personally recounted.  ',
            'variance_reason' => '  Five dollars missing after recount.  ',
            'counted_by' => $owner->id,
            'approved_by' => $owner->id,
        ])->assertOk();
        $audit = $session->fresh()->meta['closing_custody'];
        $this->assertSame($owner->id, $audit['responsible_cashier']['id']);
        $this->assertSame($manager->id, $audit['counted_by']['id']);
        $this->assertSame($manager->id, $audit['approved_by']['id']);
        $this->assertTrue($audit['on_behalf']);
        $this->assertSame('Five dollars missing after recount.', $audit['variance_reason']);
        $this->assertSame('Cashier shift finished; drawer personally recounted.', $audit['closing_reason']);
        $this->assertSame($owner->id, $session->fresh()->opened_by);
        $this->assertSame($manager->id, $session->fresh()->closed_by);
        $this->assertDatabaseCount('pos_cash_movements', 1);
        $this->getJson($this->url($session))->assertOk()
            ->assertJsonPath('body.closing_custody.counted_by.name', $manager->name)
            ->assertJsonPath('body.can_close', false);
        Event::assertDispatchedTimes(ClosePosSession::class, 1);
    }

    public function test_personal_balanced_close_records_authenticated_count_and_approval_without_reasons(): void
    {
        $session = $this->createSession();
        $actor = auth()->user();
        $preview = $this->preview($session, '500')->assertOk()
            ->assertJsonPath('body.review.requires_closing_reason', false)
            ->assertJsonPath('body.review.requires_variance_reason', false);
        $this->putJson($this->url($session).'/close', [
            'declared_cash' => '500',
            'review_token' => $preview->json('body.review.token'),
        ])->assertOk();
        $audit = $session->fresh()->meta['closing_custody'];
        $this->assertSame($actor->id, $audit['counted_by']['id']);
        $this->assertSame($actor->id, $audit['approved_by']['id']);
        $this->assertNotEmpty($audit['approved_at']);
        $this->assertFalse($audit['on_behalf']);
        $this->assertNull($audit['closing_reason']);
        $this->assertNull($audit['variance_reason']);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_reviewed_cash_variance_requires_a_trimmed_explanation_before_any_adjustment(): void
    {
        $session = $this->createSession();
        $token = $this->preview($session, '499')->assertOk()->json('body.review.token');
        foreach ([null, ' ', 'ab', str_repeat('a', 1001)] as $reason) {
            $this->putJson($this->url($session).'/close', [
                'declared_cash' => '499',
                'review_token' => $token,
                'variance_reason' => $reason,
            ])->assertUnprocessable()->assertJsonValidationErrors('variance_reason');
        }
        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
        $this->assertDatabaseCount('pos_cash_movements', 0);
        Event::assertNotDispatched(ClosePosSession::class);
    }

    public function test_permission_revocation_after_review_prevents_delegated_closing(): void
    {
        $session = $this->createSession();
        $manager = $this->cashier($session, true);
        $this->actingAs($manager);
        $token = $this->preview($session, '500')->assertOk()->json('body.review.token');
        $manager->revokePermissionTo('admin.pos_sessions.close_others');
        $this->putJson($this->url($session).'/close', [
            'declared_cash' => '500',
            'review_token' => $token,
            'closing_reason' => 'Cashier unavailable.',
        ])->assertForbidden();
        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
    }

    public function test_changed_custodian_invalidates_the_managers_review(): void
    {
        $session = $this->createSession();
        $manager = $this->cashier($session, true);
        $this->actingAs($manager);
        $token = $this->preview($session, '500')->assertOk()->json('body.review.token');
        $next = $this->cashier($session);
        $session->update(['opened_by' => $next->id]);
        $this->putJson($this->url($session).'/close', [
            'declared_cash' => '500',
            'review_token' => $token,
            'closing_reason' => 'Cashier unavailable.',
        ])->assertStatus(409);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_close_others_does_not_replace_close_permission_or_foreign_branch_access(): void
    {
        $session = $this->createSession();
        $manager = $this->cashier($session, true);
        $manager->revokePermissionTo('admin.pos_sessions.close');
        $this->actingAs($manager);
        $this->preview($session, '500')->assertForbidden();
        $this->putJson($this->url($session).'/close', ['declared_cash' => '500'])->assertForbidden();
        $manager->givePermissionTo('admin.pos_sessions.close');
        $manager->update(['branch_id' => Branch::factory()->create()->id]);
        $this->actingAs($manager->fresh());
        $this->preview($session, '500')->assertNotFound();
        $this->putJson($this->url($session).'/close', ['declared_cash' => '500'])->assertNotFound();
    }

    public function test_legacy_close_does_not_claim_an_attested_personal_cash_count(): void
    {
        $session = $this->createSession();
        $this->putJson($this->url($session).'/close', ['declared_cash' => '499'])->assertOk();
        $this->assertNull($session->fresh()->meta['closing_custody']['counted_by']);
        $this->assertSame(auth()->id(), $session->fresh()->meta['closing_custody']['approved_by']['id']);
    }

    public function test_legacy_delegated_close_still_requires_explicit_authority_and_a_reason(): void
    {
        $session = $this->createSession();
        $manager = $this->cashier($session, true);
        $this->actingAs($manager);
        $this->putJson($this->url($session).'/close', ['declared_cash' => '500'])
            ->assertUnprocessable()->assertJsonValidationErrors('closing_reason');
        $this->putJson($this->url($session).'/close', [
            'declared_cash' => '500',
            'closing_reason' => 'Responsible cashier is unavailable.',
        ])->assertOk();
        $this->assertNull($session->fresh()->meta['closing_custody']['counted_by']);
    }

    public function test_historical_closing_is_not_backfilled_or_rewritten(): void
    {
        $session = $this->createSession();
        $session->update(['status' => PosSessionStatus::Closed, 'closed_at' => now(), 'declared_cash' => 500]);
        $before = $session->fresh()->getRawOriginal();
        $this->getJson($this->url($session))->assertOk()->assertJsonPath('body.closing_custody', null);
        $this->assertSame($before, $session->fresh()->getRawOriginal());
    }

    public function test_saved_names_and_reasons_survive_later_user_changes_and_close_retries(): void
    {
        $session = $this->createSession();
        $actor = auth()->user();
        $token = $this->preview($session, '499')->assertOk()->json('body.review.token');
        $data = ['declared_cash' => '499', 'review_token' => $token, 'variance_reason' => 'Recounted drawer.'];
        $this->putJson($this->url($session).'/close', $data)->assertOk();
        $saved = $session->fresh()->meta['closing_custody'];
        $actor->update(['name' => 'Renamed cashier']);
        $this->putJson($this->url($session).'/close', [...$data, 'variance_reason' => 'Changed explanation'])->assertStatus(400);
        $this->getJson($this->url($session))->assertOk()->assertJsonPath('body.closing_custody.counted_by.name', $saved['counted_by']['name']);
        $this->assertSame($saved, $session->fresh()->meta['closing_custody']);
    }

    private function createSession(): PosSession
    {
        Event::fake([ClosePosSession::class]);
        $branch = Branch::factory()->create(['currency' => 'USD', 'cash_difference_threshold' => 10]);
        $owner = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->grantPermissions($owner);
        $this->actingAs($owner);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id]);

        return PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $owner->id,
            'opened_at' => now(),
            'opening_float' => 500,
        ]);
    }

    private function cashier(PosSession $session, bool $canCloseOthers = false): User
    {
        $user = User::factory()->create(['branch_id' => $session->branch_id, 'is_active' => true]);
        $this->grantPermissions($user);
        if ($canCloseOthers) {
            $user->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.close_others'));
        }

        return $user;
    }

    private function grantPermissions(User $user): void
    {
        foreach (['admin.pos_sessions.index', 'admin.pos_sessions.show', 'admin.pos_sessions.close'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name));
        }
    }

    private function preview(PosSession $session, string $count): TestResponse
    {
        return $this->postJson($this->url($session).'/closing-preview', ['declared_cash' => $count]);
    }

    private function url(PosSession $session): string
    {
        return '/api/v1/pos/sessions/'.$session->id;
    }
}
