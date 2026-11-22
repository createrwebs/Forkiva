<?php

use Modules\Reservation\Services\Reservation\ReservationServiceInterface;
use Modules\Reservation\Services\ReservationDeposit\ReservationDepositServiceInterface;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Services\ReservationTableBlock\ReservationTableBlockServiceInterface;
use Modules\Reservation\Services\ReservationWaitlist\ReservationWaitlistServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'reservations' => [
            Action::Index,
            Action::Show,
            Action::Create,
            Action::Edit,
            Action::Destroy,
            Action::Analytics,
            Action::Export,
            Action::Confirm,
            Action::Reject,
            Action::Cancel,
            Action::MarkArrived,
            Action::Seat,
            Action::Complete,
            Action::MarkNoShow,
            Action::AssignTable,
            Action::AutoAssignTable,
            Action::ReassignTable,
            Action::ViewAvailability,
        ],
        'reservation_settings' => [
            Action::Index,
            Action::Show,
            Action::Create,
            Action::Edit,
            Action::Destroy,
            Action::Export,
        ],
        'reservation_waitlists' => [
            Action::Index,
            Action::Show,
            Action::Create,
            Action::Edit,
            Action::Destroy,
            Action::Export,
            Action::Notify,
            Action::ConvertToReservation,
            Action::Cancel,
        ],
        'reservation_table_blocks' => [
            Action::Index,
            Action::Show,
            Action::Create,
            Action::Edit,
            Action::Destroy,
            Action::Export,
        ],
        'reservation_deposit_transactions' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Export],
    ],

    'export_resources' => [
        'reservations' => ReservationServiceInterface::class,
        'reservation_settings' => ReservationSettingServiceInterface::class,
        'reservation_waitlists' => ReservationWaitlistServiceInterface::class,
        'reservation_table_blocks' => ReservationTableBlockServiceInterface::class,
        'reservation_deposit_transactions' => ReservationDepositServiceInterface::class,
    ],
];
