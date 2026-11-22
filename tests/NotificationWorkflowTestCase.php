<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithNotificationWorkflow;

abstract class NotificationWorkflowTestCase extends TestCase
{
    use InteractsWithNotificationWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSettings();
        $this->seedRecipientRoles();
    }

    protected function tearDown(): void
    {
        try {
            $this->assertSentRenderedNotificationsHaveNoUnresolvedPlaceholders();
        } finally {
            parent::tearDown();
        }
    }
}
