<?php

namespace Modules\Support\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Jenssegers\Agent\Agent;

/**
 * @property array $agent
 */
trait HasUserAgent
{
    /**
     * Get parsed user agent
     */
    public function agent(): Attribute
    {
        return Attribute::make(get: function () {
            $userAgent = $this->getUserAgent();

            if ($userAgent) {
                $agent = tap(new Agent, function ($agent) use ($userAgent) {
                    $agent->setUserAgent($userAgent);
                });

                return [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform() ?: null,
                    'browser' => $agent->browser() ?: null,
                ];
            }

            return [
                'is_desktop' => null,
                'platform' => null,
                'browser' => null,
            ];
        });
    }

    /**
     * Get user agent
     */
    abstract public function getUserAgent(): ?string;
}
