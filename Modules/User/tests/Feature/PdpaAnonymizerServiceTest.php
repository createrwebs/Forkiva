<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\User;
use Modules\User\Services\Pdpa\PdpaAnonymizerService;
use Tests\TestCase;

class PdpaAnonymizerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_consent_and_anonymizes_pii_while_preserving_user_id_for_tax_history(): void
    {
        $user = User::factory()->create([
            'name' => 'Somying Realname',
            'email' => 'somying@example.com',
            'phone' => '0891112222',
            'address_line1' => '123 Sukhumvit Rd',
        ]);

        $service = app(PdpaAnonymizerService::class);
        $service->recordConsent($user);
        $this->assertNotNull($user->fresh()->pdpa_consent_at);

        $service->anonymize($user);
        $fresh = $user->fresh();

        $this->assertSame($user->id, $fresh->id);
        $this->assertNotNull($fresh->pdpa_anonymized_at);
        $this->assertStringStartsWith('Anonymized User #', $fresh->name);
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->address_line1);
        $this->assertStringEndsWith('@anonymized.local', (string) $fresh->email);
    }
}
