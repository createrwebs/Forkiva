<?php

namespace Modules\User\Services\TwoFactor;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TwoFactorAttemptLimiter
{
    /**
     * Count attempts using server-resolved identities, never submitted identifiers.
     */
    public function hit(string $identity, ?int $decaySeconds = null): void
    {
        $limiter = $this->limiter();
        $key = $this->key($identity);
        $maximum = (int) config('user.two_factor.max_attempts', 5);

        if ($limiter->tooManyAttempts($key, $maximum)) {
            throw new HttpException(429, __('user::auth.two_factor_too_many_attempts'), null, [
                'Retry-After' => (string) $limiter->availableIn($key),
            ]);
        }

        $limiter->hit($key, $decaySeconds ?? (int) config('user.two_factor.attempt_decay_seconds', 300));
    }

    /**
     * Reset only after successful verification of the corresponding credential.
     */
    public function clear(string $identity): void
    {
        $this->limiter()->clear($this->key($identity));
    }

    protected function limiter(): RateLimiter
    {
        return new RateLimiter(Cache::store(Config::string('user.two_factor.cache_store')));
    }

    protected function key(string $identity): string
    {
        return 'two-factor:attempts:'.hash('sha256', $identity);
    }
}
