<?php

namespace Modules\Notification\Support;

readonly class GuestNotifiable
{
    /**
     * Create a new guest notifiable instance.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $phoneCountryIsoCode = null,
    ) {}

    /**
     * Determine whether the guest has any contact route.
     */
    public function hasContactRoute(): bool
    {
        return filled($this->email) || filled($this->phone);
    }

    /**
     * Route email notifications.
     *
     * @return array<string, string>|string|null
     */
    public function routeNotificationForMail(?object $notification = null): array|string|null
    {
        if (blank($this->email)) {
            return null;
        }

        if (blank($this->name)) {
            return $this->email;
        }

        return [$this->email => $this->name];
    }

    /**
     * Route SMS notifications.
     */
    public function routeNotificationForSms(?object $notification = null): ?string
    {
        return filled($this->phone) ? $this->phone : null;
    }

    /**
     * Route WhatsApp notifications.
     */
    public function routeNotificationForWhatsapp(?object $notification = null): ?string
    {
        return filled($this->phone) ? $this->phone : null;
    }
}
