<?php

namespace Modules\User\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ActivityLog\Models\AuthenticationLog;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\User\Models\User;

class SendNewDeviceLoginNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int|string $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public string $locale,
        public Carbon $occurredAt,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationDispatcherServiceInterface $dispatcher): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $log = $this->authenticationLog();
        if (! $log || $log->login_at === null || $this->isKnownDevice($log) || $this->isNewUser($user, $log)) {
            return;
        }

        app()->setLocale($this->locale);

        $dispatcher->send(
            templateKey: 'new_device_login_detected',
            notifiables: $user,
            variables: [
                'user_name' => $user->name,
                'login_time' => dateTimeFormat($log->login_at),
                'device_name' => NotificationVariableValue::deviceName($log->agent),
                'location' => '-',
                'ip_address' => $log->ip_address ?: '-',
                'app_name' => NotificationVariableValue::appName(),
            ],
            locale: $this->locale
        );
    }

    /**
     * Resolve the authentication log saved by the third-party package.
     */
    protected function authenticationLog(): ?AuthenticationLog
    {
        $from = $this->occurredAt->copy()->subSeconds(10);
        $to = $this->occurredAt->copy()->addSeconds(10);

        $logs = $this->baseAuthenticationLogQuery()
            ->whereBetween('login_at', [$from, $to])
            ->get();

        if ($logs->isEmpty()) {
            return $this->baseAuthenticationLogQuery()
                ->latest('login_at')
                ->first();
        }

        return $logs
            ->sortBy(fn (AuthenticationLog $log) => abs($log->login_at->getTimestamp() - $this->occurredAt->getTimestamp()))
            ->first();
    }

    /**
     * Build the base authentication log query for this login.
     */
    protected function baseAuthenticationLogQuery(): Builder
    {
        return AuthenticationLog::query()
            ->where('authenticatable_type', User::class)
            ->where('authenticatable_id', $this->userId)
            ->where('ip_address', $this->ipAddress)
            ->where('user_agent', $this->userAgent);
    }

    /**
     * Determine if this IP and user agent were already known before the matched log.
     */
    protected function isKnownDevice(AuthenticationLog $log): bool
    {
        return $this->baseAuthenticationLogQuery()
            ->where('id', '!=', $log->id)
            ->where(function (Builder $query) use ($log) {
                $query->where('login_at', '<', $log->login_at)
                    ->orWhere(function (Builder $query) use ($log) {
                        $query->where('login_at', $log->login_at)
                            ->where('id', '<', $log->id);
                    });
            })
            ->exists();
    }

    /**
     * Determine if the login belongs to a just-created account.
     */
    protected function isNewUser(User $user, AuthenticationLog $log): bool
    {
        return $user->created_at && $user->created_at->diffInMinutes($log->login_at) < 1;
    }
}
