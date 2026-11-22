<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Setting\Repositories\SettingRepository;
use Modules\User\Models\User;
use Modules\User\Notifications\TwoFactorCodeNotification;
use Modules\User\Services\TwoFactor\TwoFactorService;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Throwable;

class TwoFactorMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! ((env('RUN_TWO_FACTOR_MYSQL_TESTS') && DB::getDriverName() === 'mysql')
            || (env('RUN_TWO_FACTOR_PGSQL_TESTS') && DB::getDriverName() === 'pgsql'))) {
            $this->markTestSkipped('Opt in with RUN_TWO_FACTOR_MYSQL_TESTS=1 or RUN_TWO_FACTOR_PGSQL_TESTS=1 and a matching disposable database.');
        }
        $this->assertTrue(app()->environment('testing'));
        $this->assertStringEndsWith('_test', DB::getDatabaseName());
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('The concurrency gate requires pcntl and local sockets.');
        }

        // Never run this opt-in gate against application or shared development data.
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        config([
            'cache.stores.two_factor_concurrency' => [
                'driver' => 'file',
                'path' => sys_get_temp_dir().'/forkiva-two-factor-test-cache-'.bin2hex(random_bytes(8)),
            ],
            'user.two_factor.cache_store' => 'two_factor_concurrency',
        ]);
        $values = [
            'two_factor_enabled' => true,
            'two_factor_authenticator_enabled' => true,
            'two_factor_email_enabled' => true,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'log',
        ];
        app()->instance('setting', new SettingRepository(collect($values)->map(fn ($value) => [
            'payload' => serialize($value), 'is_encryptable' => false, 'is_translatable' => false,
        ])));
        Notification::fake();
    }

    public function test_parallel_requests_cannot_reuse_totp_delivered_or_recovery_codes(): void
    {
        foreach (['authenticator', 'email', 'recovery', 'authenticator_separate_challenges'] as $case) {
            $user = User::factory()->create(['is_active' => true]);
            $user->forceFill([
                'two_factor_method' => $case === 'email' ? 'email' : 'authenticator',
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
                'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')],
                'two_factor_confirmed_at' => now(),
            ])->save();
            $service = app(TwoFactorService::class);
            $challenge = $service->createLoginChallenge($user);
            $challenges = [
                $challenge,
                $case === 'authenticator_separate_challenges' ? $service->createLoginChallenge($user) : $challenge,
            ];
            $code = match ($case) {
                'email' => (new ReflectionProperty(TwoFactorCodeNotification::class, 'code'))
                    ->getValue(Notification::sent($user, TwoFactorCodeNotification::class)->last()),
                'recovery' => 'ABCDE-12345',
                default => (new ReflectionMethod($service, 'totpAt'))->invoke(
                    $service, 'JBSWY3DPEHPK3PXP', (int) floor(now()->getTimestamp() / 30),
                ),
            };

            DB::disconnect();
            $workers = [];
            for ($index = 0; $index < 2; $index++) {
                [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
                stream_set_timeout($parent, 15);
                stream_set_timeout($child, 15);
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    fclose($parent);
                    DB::purge();
                    DB::reconnect();
                    DB::statement(DB::getDriverName() === 'pgsql'
                        ? "SET lock_timeout = '5s'"
                        : 'SET SESSION innodb_lock_wait_timeout = 5');
                    fwrite($child, "ready\n");
                    if (trim((string) fgets($child)) !== 'go') {
                        exit(1);
                    }
                    try {
                        app(TwoFactorService::class)->verifyLoginChallenge($challenges[$index]['challenge_token'], $code);
                        fwrite($child, "accepted\n");
                    } catch (HttpExceptionInterface $exception) {
                        fwrite($child, $exception->getStatusCode() === 422 ? "rejected\n" : "unexpected-http-status\n");
                    } catch (Throwable) {
                        // Never copy exception text, OTPs or account data into test output.
                        fwrite($child, "unexpected-error\n");
                    }
                    fclose($child);
                    exit(0);
                }
                fclose($child);
                $workers[] = [$pid, $parent];
            }
            foreach ($workers as [$pid, $socket]) {
                $this->assertSame("ready\n", fgets($socket));
            }
            foreach ($workers as [$pid, $socket]) {
                fwrite($socket, "go\n");
            }
            $results = [];
            foreach ($workers as [$pid, $socket]) {
                $results[] = trim((string) fgets($socket));
                fclose($socket);
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status));
                $this->assertSame(0, pcntl_wexitstatus($status));
            }
            DB::purge();
            DB::reconnect();
            sort($results);
            $this->assertSame(['accepted', 'rejected'], $results, $case);
        }
    }
}
