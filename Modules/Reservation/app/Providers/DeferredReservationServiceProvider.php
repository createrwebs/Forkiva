<?php

namespace Modules\Reservation\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Reservation\Services\Reservation\ReservationService;
use Modules\Reservation\Services\Reservation\ReservationServiceInterface;
use Modules\Reservation\Services\ReservationAnalytics\ReservationAnalyticsService;
use Modules\Reservation\Services\ReservationAnalytics\ReservationAnalyticsServiceInterface;
use Modules\Reservation\Services\ReservationAvailability\ReservationAvailabilityService;
use Modules\Reservation\Services\ReservationAvailability\ReservationAvailabilityServiceInterface;
use Modules\Reservation\Services\ReservationDeposit\ReservationDepositService;
use Modules\Reservation\Services\ReservationDeposit\ReservationDepositServiceInterface;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationService;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;
use Modules\Reservation\Services\ReservationNumber\ReservationNumberService;
use Modules\Reservation\Services\ReservationNumber\ReservationNumberServiceInterface;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderService;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingService;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Services\ReservationStatus\ReservationStatusService;
use Modules\Reservation\Services\ReservationStatus\ReservationStatusServiceInterface;
use Modules\Reservation\Services\ReservationTableAssignment\ReservationTableAssignmentService;
use Modules\Reservation\Services\ReservationTableAssignment\ReservationTableAssignmentServiceInterface;
use Modules\Reservation\Services\ReservationTableBlock\ReservationTableBlockService;
use Modules\Reservation\Services\ReservationTableBlock\ReservationTableBlockServiceInterface;
use Modules\Reservation\Services\ReservationWaitlist\ReservationWaitlistService;
use Modules\Reservation\Services\ReservationWaitlist\ReservationWaitlistServiceInterface;

class DeferredReservationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        foreach ($this->bindings() as $abstract => $concrete) {
            $this->app->singleton($abstract, fn ($app) => $app->make($concrete));
        }
    }

    /**
     * Get provided services.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_keys($this->bindings());
    }

    /**
     * Get service bindings.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            ReservationServiceInterface::class => ReservationService::class,
            ReservationAvailabilityServiceInterface::class => ReservationAvailabilityService::class,
            ReservationNotificationServiceInterface::class => ReservationNotificationService::class,
            ReservationNumberServiceInterface::class => ReservationNumberService::class,
            ReservationReminderServiceInterface::class => ReservationReminderService::class,
            ReservationAnalyticsServiceInterface::class => ReservationAnalyticsService::class,
            ReservationDepositServiceInterface::class => ReservationDepositService::class,
            ReservationSettingServiceInterface::class => ReservationSettingService::class,
            ReservationStatusServiceInterface::class => ReservationStatusService::class,
            ReservationTableAssignmentServiceInterface::class => ReservationTableAssignmentService::class,
            ReservationTableBlockServiceInterface::class => ReservationTableBlockService::class,
            ReservationWaitlistServiceInterface::class => ReservationWaitlistService::class,
        ];
    }
}
