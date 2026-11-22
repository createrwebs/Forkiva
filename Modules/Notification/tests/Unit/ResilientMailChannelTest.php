<?php

namespace Modules\Notification\Tests\Unit;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Mockery;
use Modules\Notification\Channels\ResilientMailChannel;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use RuntimeException;
use Tests\TestCase;

class ResilientMailChannelTest extends TestCase
{
    public function test_notification_module_replaces_the_framework_mail_channel(): void
    {
        $this->assertInstanceOf(ResilientMailChannel::class, app(MailChannel::class));
    }

    public function test_rendered_notification_does_not_propagate_mail_transport_failure(): void
    {
        $channel = $this->failingChannel();
        $notifiable = (new AnonymousNotifiable)->route('mail', 'customer@example.com');
        $notification = new RenderedTemplateNotification([
            'email' => [
                'subject' => 'Order updated',
                'content' => 'Your order was updated.',
            ],
        ], 'Forkiva');

        $this->assertNull($channel->send($notifiable, $notification));
    }

    public function test_other_notification_mail_transport_failure_is_not_suppressed(): void
    {
        $channel = $this->failingChannel();
        $notifiable = (new AnonymousNotifiable)->route('mail', 'customer@example.com');
        $notification = new class extends Notification
        {
            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->subject('Security code')->line('123456');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP unavailable');

        $channel->send($notifiable, $notification);
    }

    private function failingChannel(): ResilientMailChannel
    {
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->andThrow(new RuntimeException('SMTP unavailable'));

        $factory = Mockery::mock(MailFactory::class);
        $factory->shouldReceive('mailer')->andReturn($mailer);

        return new ResilientMailChannel($factory, Mockery::mock(Markdown::class));
    }
}
