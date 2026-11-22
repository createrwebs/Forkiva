<?php

namespace Modules\Notification\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Enums\AccountingHealthFindingSeverity;
use Modules\Accounting\Models\AccountingHealthFinding;
use Modules\Accounting\Notifications\AccountingReliabilityAlert;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Notification\Support\EmailBranding;
use Modules\Setting\Repositories\SettingRepository;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Notifications\PasswordRecoveryCodeNotification;
use Modules\User\Notifications\TwoFactorCodeNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailDesignTest extends TestCase
{
    public static function emailCases(): iterable
    {
        foreach (['workflow', 'recovery', 'two-factor', 'accounting'] as $kind) {
            foreach (['en', 'ar'] as $locale) {
                yield "$kind-$locale" => [$kind, $locale];
            }
        }
    }

    public static function verificationCases(): iterable
    {
        foreach (['recovery', 'two-factor'] as $kind) {
            foreach (['en', 'ar'] as $locale) {
                yield "$kind-$locale" => [$kind, $locale];
            }
        }
    }

    #[DataProvider('verificationCases')]
    public function test_verification_emails_present_one_code_with_expiry_in_html_and_plain_text(string $kind, string $locale): void
    {
        app()->setLocale($locale);
        $notification = $kind === 'recovery'
            ? new PasswordRecoveryCodeNotification('012345', 'email')
            : new TwoFactorCodeNotification('012345', TwoFactorMethod::Email, 5);

        $email = $this->deliver($notification);
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $expiry = __('notification::email.expires', ['minutes' => $kind === 'recovery' ? 10 : 5]);

        $this->assertSame(1, substr_count($html, '012345'));
        $this->assertSame(1, substr_count($text, '012345'));
        $this->assertStringContainsString('email-code-value', $html);
        $this->assertMatchesRegularExpression('/class="email-code-value"[^>]*dir="ltr"/', $html);
        $this->assertStringContainsString($expiry, $html);
        $this->assertStringContainsString($expiry, $text);
        $this->assertStringContainsString(__('notification::email.security'), $html);
        $this->assertStringNotContainsString('<table', $text);
        if ($kind === 'recovery') {
            $this->assertStringContainsString(__('user::auth.recovery_ignore'), $text);
        }
    }

    #[DataProvider('emailCases')]
    public function test_every_email_path_has_localized_branding_and_a_data_uri_logo(string $kind, string $locale): void
    {
        app()->setLocale($locale);

        $notification = match ($kind) {
            'recovery' => new PasswordRecoveryCodeNotification('123456', 'email'),
            'two-factor' => new TwoFactorCodeNotification('123456', TwoFactorMethod::Email, 5),
            'accounting' => new AccountingReliabilityAlert(new AccountingHealthFinding([
                'title' => 'Ledger requires attention',
                'message' => 'A ledger discrepancy was detected.',
                'branch_id' => 7,
                'check_key' => 'ledger_balance',
                'severity' => AccountingHealthFindingSeverity::Critical,
                'first_seen_at' => now(),
            ])),
            default => $this->workflow(),
        };

        $email = $this->deliver($notification);
        $html = $email->getHtmlBody();

        $this->assertStringContainsString('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', $html);
        $this->assertStringContainsString($locale === 'ar' ? 'مطبخ فوركيفا' : 'Forkiva Kitchen', $html);
        $this->assertStringContainsString('email-card', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('cid:', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
        $this->assertStringNotContainsString('notification::email.', $html);
        $this->assertStringContainsString($email->getSubject(), html_entity_decode(strip_tags($html)));
        $this->assertNotEmpty($email->getTextBody());
        $this->assertStringContainsString($locale === 'ar' ? 'مطبخ فوركيفا' : 'Forkiva Kitchen', $email->getTextBody());
        $this->assertStringContainsString($email->getSubject(), $email->getTextBody());
        $this->assertStringNotContainsString('<table', $email->getTextBody());
        $this->assertCount(0, $email->getAttachments());

        if (in_array($kind, ['recovery', 'two-factor'], true)) {
            $this->assertStringContainsString('123456', $html);
            $this->assertStringContainsString('123456', $email->getTextBody());
        }
    }

    private function workflow(): RenderedTemplateNotification
    {
        return new RenderedTemplateNotification(['email' => [
            'translations' => [
                'en' => ['subject' => '<Order> & update', 'content' => '<p><strong>Ready</strong></p><p>Collect your order.</p>'],
                'ar' => ['subject' => 'تحديث الطلب', 'content' => '<p><strong>طلبك جاهز</strong></p><p>يرجى استلام طلبك.</p>'],
            ],
        ]], 'Forkiva');
    }

    private function deliver(Notification $notification): Email
    {
        $recipient = (new AnonymousNotifiable)->route('mail', 'email-design@example.com');
        $sent = app(MailChannel::class)->send($recipient, $notification);

        return $sent->getOriginalMessage();
    }

    public function test_workflow_preserves_formatting_escapes_subjects_and_provides_readable_text(): void
    {
        app()->setLocale('en');
        $email = $this->deliver($this->workflow());

        $this->assertMatchesRegularExpression('/<strong\b[^>]*>Ready<\/strong>/', $email->getHtmlBody());
        $this->assertStringContainsString('&lt;Order&gt; &amp; update', $email->getHtmlBody());
        $this->assertStringNotContainsString('<script', $email->getHtmlBody());
        $this->assertStringContainsString("Ready\nCollect your order.", $email->getTextBody());
        $this->assertStringNotContainsString('<strong>', $email->getTextBody());
        $this->assertStringContainsString('font-size: 16px', $email->getHtmlBody());
    }

    public function test_configured_email_logo_is_rendered_as_a_data_uri(): void
    {
        $this->settings(['email_logo_mime_type' => 'image/gif']);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        Storage::disk('local')->put('email-logo.base64', base64_encode($gif));

        $email = $this->deliver($this->workflow());

        $this->assertStringContainsString(
            'data:image/gif;base64,'.base64_encode($gif),
            $email->getHtmlBody(),
        );
        $this->assertCount(0, $email->getAttachments());
    }

    public function test_workflow_html_does_not_allow_unsafe_template_markup(): void
    {
        app()->setLocale('en');
        $notification = new RenderedTemplateNotification(['email' => ['translations' => [
            'en' => ['subject' => 'Update', 'content' => '<p onclick="alert(1)">Safe text</p><script>alert(1)</script><img src="x" onerror="alert(1)">'],
        ]]], 'Forkiva');

        $html = $this->deliver($notification)->getHtmlBody();

        $this->assertStringContainsString('Safe text', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_email_logo_takes_priority_over_the_default_email_logo(): void
    {
        $this->settings(['email_logo_mime_type' => 'image/gif']);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        Storage::disk('local')->put('email-logo.base64', base64_encode($gif));
        Storage::disk('local')->put('default-email-logo.base64', base64_encode(file_get_contents(public_path('assets/logo.png'))));

        $email = $this->deliver($this->workflow());

        $this->assertStringContainsString(
            'data:image/gif;base64,'.base64_encode($gif),
            $email->getHtmlBody(),
        );
    }

    public function test_empty_or_missing_email_logo_falls_back_to_the_default_email_logo(): void
    {
        $expected = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('assets/logo.png')));

        Storage::disk('local')->put('email-logo.base64', '');
        $this->assertSame($expected, app(EmailBranding::class)->logo());

        Storage::disk('local')->delete('email-logo.base64');
        $this->assertSame($expected, app(EmailBranding::class)->logo());
    }

    public function test_logo_uses_the_configured_email_mime_type(): void
    {
        $this->settings(['email_logo_mime_type' => 'image/webp']);
        Storage::disk('local')->put('email-logo.base64', 'configured-logo');

        $this->assertSame('data:image/webp;base64,configured-logo', app(EmailBranding::class)->logo());
    }

    public function test_standard_action_links_outro_and_salutation_survive_the_shared_layout(): void
    {
        $notification = new class extends Notification
        {
            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)
                    ->subject('Review your export')
                    ->line('Your export is ready.')
                    ->action('Download export', 'https://example.com/exports/123')
                    ->line('The download requires sign in.')
                    ->salutation('The support team');
            }
        };

        $email = $this->deliver($notification);

        foreach (['https://example.com/exports/123', 'Download export', 'The download requires sign in.', 'The support team'] as $value) {
            $this->assertStringContainsString($value, $email->getHtmlBody());
            $this->assertStringContainsString($value, $email->getTextBody());
        }
        $this->assertStringContainsString('subcopy', $email->getHtmlBody());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings();
        config(['mail.default' => 'array', 'filesystems.default_private' => 'email_test']);
        Storage::fake('email_test');
        Storage::fake('local');
        Storage::disk('local')->put(
            'default-email-logo.base64',
            base64_encode(file_get_contents(public_path('assets/logo.png'))),
        );
    }

    private function settings(array $values = []): void
    {
        app()->instance('setting', new SettingRepository(collect(array_merge([
            'app_name' => ['en' => 'Forkiva Kitchen', 'ar' => 'مطبخ فوركيفا'],
            'default_locale' => 'en',
            'supported_locales' => ['en', 'ar'],
        ], $values))->map(fn (mixed $value, string $key): array => [
            'payload' => serialize($value),
            'is_encryptable' => false,
            'is_translatable' => $key === 'app_name',
        ])));
    }
}
