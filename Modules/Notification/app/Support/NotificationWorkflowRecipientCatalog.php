<?php

namespace Modules\Notification\Support;

use Modules\User\Enums\DefaultRole;

class NotificationWorkflowRecipientCatalog
{
    private const EXTERNAL_CHANNELS = ['email', 'sms', 'whatsapp'];

    private const DEFAULT_INTERACTIVE_CHANNELS = ['in_app', 'push_notification'];

    private const DEFAULT_DIRECT_CHANNELS = ['email', 'in_app', 'push_notification'];

    private const ACCOUNT_CREATED_TEMPLATES = [
        'account_created',
        'account_activated',
    ];

    private const ACCOUNT_SECURITY_TEMPLATES = [
        'profile_updated',
        'password_changed',
        'new_device_login_detected',
        'suspicious_login_detected',
    ];

    private const ORDER_CUSTOMER_TEMPLATES = [
        'order_created',
        'order_updated',
        'order_status_changed',
        'kitchen_item_ready',
    ];

    private const ORDER_CASH_TEMPLATES = [
        'order_cancelled',
        'order_refunded',
        'order_voided',
    ];

    private const ORDER_PAYMENT_TEMPLATES = [
        'order_payment_completed',
    ];

    private const DELIVERY_ASSIGNMENT_TEMPLATES = [
        'delivery_assigned',
        'delivery_reassigned',
    ];

    private const DELIVERY_RESPONSE_TEMPLATES = [
        'driver_delivery_accepted',
        'driver_delivery_rejected',
        'driver_batch_accepted',
    ];

    private const DELIVERY_STATUS_TEMPLATES = [
        'delivery_status_changed',
    ];

    private const EXTERNAL_COURIER_TEMPLATES = [
        'external_courier_dispatch_updated',
    ];

    private const DRIVER_TRACKING_TEMPLATES = [
        'driver_tracking_stale',
        'driver_tracking_offline',
        'driver_tracking_permission_issue',
        'driver_tracking_token_issue',
    ];

    private const DRIVER_SETTLEMENT_TEMPLATES = [
        'driver_cod_declared',
        'driver_cash_settlement_updated',
    ];

    private const DRIVER_EARNING_TEMPLATES = [
        'driver_earnings_updated',
        'driver_payout_updated',
    ];

    private const DRIVER_PAYOUT_ISSUE_TEMPLATES = [
        'driver_payout_issue_reported',
        'driver_payout_issue_resolved',
    ];

    private const HR_PROFILE_TEMPLATES = [
        'employee_created',
        'employment_profile_updated',
    ];

    private const HR_ATTENDANCE_TEMPLATES = [
        'attendance_event_recorded',
        'attendance_manual_adjusted',
        'attendance_record_lock_changed',
    ];

    private const HR_REQUEST_TEMPLATES = [
        'leave_request_submitted',
        'leave_request_approved',
        'leave_request_rejected',
        'leave_request_cancelled',
        'overtime_request_submitted',
        'overtime_request_approved',
        'overtime_request_rejected',
        'overtime_request_cancelled',
    ];

    private const PAYROLL_TEMPLATES = [
        'payroll_run_updated',
        'payslip_updated',
        'payroll_payment_updated',
    ];

    private const EMPLOYEE_DOCUMENT_TEMPLATES = [
        'employee_document_available',
        'payslip_file_available',
    ];

    private const POS_TEMPLATES = [
        'pos_session_opened',
        'pos_session_closed',
        'pos_cash_variance_found',
    ];

    private const TABLE_TEMPLATES = [
        'table_assigned_to_waiter',
        'table_status_changed',
    ];

    private const CUSTOMER_VALUE_TEMPLATES = [
        'gift_card_issued',
        'gift_card_redeemed',
        'gift_card_recharged',
        'gift_card_adjusted',
        'gift_card_expired',
        'loyalty_points_changed',
        'loyalty_reward_redeemed',
        'loyalty_tier_changed',
    ];

    private const RESERVATION_TEMPLATES = [
        'reservation_created',
        'reservation_updated',
        'reservation_confirmed',
        'reservation_cancelled',
        'reservation_arrived',
        'reservation_seated',
        'reservation_completed',
        'reservation_no_show',
        'reservation_expired',
        'reservation_rejected',
        'reservation_table_assigned',
        'reservation_reminder',
        'reservation_waitlist_notified',
        'reservation_waitlist_converted',
    ];

    private const INVENTORY_TEMPLATES = [
        'inventory_purchase_updated',
        'stock_movement_needs_attention',
        'stock_transfer_status_changed',
    ];

    private const EXPORT_TEMPLATES = [
        'export_completed',
        'export_failed',
    ];

    /**
     * Get default enabled recipient roles for a workflow channel.
     *
     * @return array<int, string>
     */
    public static function defaultRolesFor(string $workflowKey, string $channel): array
    {
        $roles = match (true) {
            in_array($workflowKey, self::ACCOUNT_CREATED_TEMPLATES, true) => self::defaultAccountCreatedRoles($channel),
            in_array($workflowKey, self::ACCOUNT_SECURITY_TEMPLATES, true) => self::defaultAccountSecurityRoles($channel),
            in_array($workflowKey, self::ORDER_CUSTOMER_TEMPLATES, true) => self::defaultOrderRoles($workflowKey, $channel),
            in_array($workflowKey, self::ORDER_CASH_TEMPLATES, true) => self::defaultOrderCashRoles($workflowKey, $channel),
            in_array($workflowKey, self::ORDER_PAYMENT_TEMPLATES, true) => self::defaultDirectRoles($channel, [DefaultRole::Customer->value]),
            in_array($workflowKey, self::DELIVERY_ASSIGNMENT_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Customer->value,
                DefaultRole::Driver->value,
            ]),
            in_array($workflowKey, self::DELIVERY_RESPONSE_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Dispatcher->value,
            ]),
            in_array($workflowKey, self::DELIVERY_STATUS_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Customer->value,
            ]),
            in_array($workflowKey, self::EXTERNAL_COURIER_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Dispatcher->value,
                DefaultRole::ExternalCourierOperator->value,
            ]),
            in_array($workflowKey, self::DRIVER_TRACKING_TEMPLATES, true) => self::defaultDriverTrackingRoles($workflowKey, $channel),
            in_array($workflowKey, self::DRIVER_SETTLEMENT_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Driver->value,
            ]),
            in_array($workflowKey, self::DRIVER_EARNING_TEMPLATES, true) => self::defaultDirectRoles($channel, [
                DefaultRole::Driver->value,
            ]),
            in_array($workflowKey, self::DRIVER_PAYOUT_ISSUE_TEMPLATES, true) => self::defaultDriverPayoutIssueRoles($workflowKey, $channel),
            in_array($workflowKey, self::HR_PROFILE_TEMPLATES, true) => self::defaultEmployeeDirectRoles($channel),
            in_array($workflowKey, self::HR_ATTENDANCE_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Staff->value,
            ]),
            in_array($workflowKey, self::HR_REQUEST_TEMPLATES, true) => self::defaultHrRequestRoles($workflowKey, $channel),
            in_array($workflowKey, self::PAYROLL_TEMPLATES, true) => self::defaultPayrollRoles($workflowKey, $channel),
            in_array($workflowKey, self::EMPLOYEE_DOCUMENT_TEMPLATES, true) => self::defaultEmployeeDirectRoles($channel),
            in_array($workflowKey, self::POS_TEMPLATES, true) => self::defaultPosRoles($workflowKey, $channel),
            in_array($workflowKey, self::TABLE_TEMPLATES, true) => self::defaultInteractiveRoles($channel, [
                DefaultRole::Waiter->value,
            ]),
            in_array($workflowKey, self::CUSTOMER_VALUE_TEMPLATES, true) => self::defaultDirectRoles($channel, [
                DefaultRole::Customer->value,
            ]),
            in_array($workflowKey, self::RESERVATION_TEMPLATES, true) => self::defaultReservationRoles($workflowKey, $channel),
            in_array($workflowKey, self::INVENTORY_TEMPLATES, true) => self::defaultInventoryRoles($workflowKey, $channel),
            in_array($workflowKey, self::EXPORT_TEMPLATES, true) => self::defaultExportRoles($channel),
            default => [],
        };

        return array_values(array_intersect(
            self::allowedRolesFor($workflowKey, $channel),
            $roles
        ));
    }

    /**
     * Get default roles for account creation and activation.
     *
     * @return array<int, string>
     */
    protected static function defaultAccountCreatedRoles(string $channel): array
    {
        return self::rolesForDefaultChannels($channel, ['email', 'in_app'], [
            DefaultRole::Customer->value,
            DefaultRole::Staff->value,
        ]);
    }

    /**
     * Return roles when a channel is enabled by default.
     *
     * @param  array<int, string>  $defaultChannels
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    protected static function rolesForDefaultChannels(string $channel, array $defaultChannels, array $roles): array
    {
        if (! in_array($channel, $defaultChannels, true)) {
            return [];
        }

        return $roles;
    }

    /**
     * Get default account security notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultAccountSecurityRoles(string $channel): array
    {
        return self::defaultDirectRoles($channel, self::accountOwnerRoles());
    }

    /**
     * Return roles only for default direct channels.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    protected static function defaultDirectRoles(string $channel, array $roles): array
    {
        return self::rolesForDefaultChannels($channel, self::DEFAULT_DIRECT_CHANNELS, $roles);
    }

    /**
     * Get account-owner roles without allowing every custom role.
     *
     * @return array<int, string>
     */
    protected static function accountOwnerRoles(): array
    {
        return [
            DefaultRole::Admin->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Manager->value,
            DefaultRole::Cashier->value,
            DefaultRole::Kitchen->value,
            DefaultRole::Waiter->value,
            DefaultRole::Customer->value,
            DefaultRole::HrManager->value,
            DefaultRole::HrOfficer->value,
            DefaultRole::Staff->value,
        ];
    }

    /**
     * Get default order notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultOrderRoles(string $workflowKey, string $channel): array
    {
        if ($workflowKey === 'kitchen_item_ready') {
            return self::defaultInteractiveRoles($channel, [
                DefaultRole::Kitchen->value,
                DefaultRole::Waiter->value,
            ]);
        }

        if (in_array($workflowKey, ['order_updated', 'order_status_changed'], true)) {
            return self::defaultInteractiveRoles($channel, [
                DefaultRole::Customer->value,
            ]);
        }

        return self::defaultDirectRoles($channel, [
            DefaultRole::Customer->value,
        ]);
    }

    /**
     * Return roles only for low-noise interactive channels.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    protected static function defaultInteractiveRoles(string $channel, array $roles): array
    {
        return self::rolesForDefaultChannels($channel, self::DEFAULT_INTERACTIVE_CHANNELS, $roles);
    }

    /**
     * Get default cash/refund/void order notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultOrderCashRoles(string $workflowKey, string $channel): array
    {
        return self::defaultDirectRoles($channel, [
            DefaultRole::Customer->value,
        ]);
    }

    /**
     * Get default driver tracking recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultDriverTrackingRoles(string $workflowKey, string $channel): array
    {
        $roles = in_array($workflowKey, ['driver_tracking_permission_issue', 'driver_tracking_token_issue'], true)
            ? [DefaultRole::Driver->value]
            : [DefaultRole::Dispatcher->value];

        return self::defaultInteractiveRoles($channel, $roles);
    }

    /**
     * Get default driver payout issue recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultDriverPayoutIssueRoles(string $workflowKey, string $channel): array
    {
        $channels = $workflowKey === 'driver_payout_issue_resolved'
            ? self::DEFAULT_DIRECT_CHANNELS
            : self::DEFAULT_INTERACTIVE_CHANNELS;

        return self::rolesForDefaultChannels($channel, $channels, [
            DefaultRole::Driver->value,
        ]);
    }

    /**
     * Get default direct employee notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultEmployeeDirectRoles(string $channel): array
    {
        return self::defaultDirectRoles($channel, [
            DefaultRole::Staff->value,
        ]);
    }

    /**
     * Get default HR request recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultHrRequestRoles(string $workflowKey, string $channel): array
    {
        if (in_array($workflowKey, ['leave_request_submitted', 'overtime_request_submitted'], true)) {
            return self::defaultInteractiveRoles($channel, [
                DefaultRole::Staff->value,
                DefaultRole::HrManager->value,
                DefaultRole::HrOfficer->value,
            ]);
        }

        return self::defaultDirectRoles($channel, [
            DefaultRole::Staff->value,
        ]);
    }

    /**
     * Get default payroll notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultPayrollRoles(string $workflowKey, string $channel): array
    {
        if ($workflowKey === 'payroll_run_updated') {
            return self::rolesForDefaultChannels($channel, ['email', 'in_app'], [
                DefaultRole::Staff->value,
                DefaultRole::Payroll->value,
                DefaultRole::HrManager->value,
                DefaultRole::HrOfficer->value,
            ]);
        }

        return self::defaultDirectRoles($channel, [
            DefaultRole::Staff->value,
        ]);
    }

    /**
     * Get default POS notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultPosRoles(string $workflowKey, string $channel): array
    {
        $roles = $workflowKey === 'pos_cash_variance_found'
            ? [
                DefaultRole::Cashier->value,
                DefaultRole::Manager->value,
                DefaultRole::AdminBranch->value,
            ]
            : [DefaultRole::Cashier->value];

        return self::defaultInteractiveRoles($channel, $roles);
    }

    /**
     * Get default reservation notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultReservationRoles(string $workflowKey, string $channel): array
    {
        $staffEvents = [
            'reservation_created',
            'reservation_arrived',
            'reservation_seated',
            'reservation_table_assigned',
            'reservation_waitlist_notified',
            'reservation_waitlist_converted',
        ];

        if (in_array($workflowKey, $staffEvents, true)) {
            return self::defaultInteractiveRoles($channel, [
                DefaultRole::Waiter->value,
                DefaultRole::Cashier->value,
                DefaultRole::Manager->value,
                DefaultRole::AdminBranch->value,
            ]);
        }

        return self::defaultDirectRoles($channel, [
            DefaultRole::Customer->value,
        ]);
    }

    /**
     * Get default inventory notification recipients.
     *
     * @return array<int, string>
     */
    protected static function defaultInventoryRoles(string $workflowKey, string $channel): array
    {
        $channels = $workflowKey === 'stock_movement_needs_attention'
            ? self::DEFAULT_DIRECT_CHANNELS
            : self::DEFAULT_INTERACTIVE_CHANNELS;

        return self::rolesForDefaultChannels($channel, $channels, [
            DefaultRole::Inventory->value,
            DefaultRole::Procurement->value,
        ]);
    }

    /**
     * Get default export notification recipients.
     *
     * Export notifications are sent to the requesting user only, so the enabled
     * roles represent requester role eligibility rather than broadcast targets.
     *
     * @return array<int, string>
     */
    protected static function defaultExportRoles(string $channel): array
    {
        return self::rolesForDefaultChannels($channel, ['email', 'in_app'], self::exportRoles());
    }

    /**
     * Get export recipient roles.
     *
     * @return array<int, string>
     */
    protected static function exportRoles(): array
    {
        return [
            DefaultRole::Admin->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Manager->value,
            DefaultRole::HrManager->value,
            DefaultRole::HrOfficer->value,
            DefaultRole::Staff->value,
            DefaultRole::Inventory->value,
            DefaultRole::Procurement->value,
        ];
    }

    /**
     * Get allowed recipient roles for a workflow channel.
     *
     * @return array<int, string>
     */
    public static function allowedRolesFor(string $workflowKey, string $channel): array
    {
        return array_values(array_unique(match (true) {
            in_array($workflowKey, self::ACCOUNT_CREATED_TEMPLATES, true) => self::accountCreatedRoles($channel),
            in_array($workflowKey, self::ACCOUNT_SECURITY_TEMPLATES, true) => self::accountOwnerRoles(),
            in_array($workflowKey, self::ORDER_CUSTOMER_TEMPLATES, true) => self::orderRoles($channel),
            in_array($workflowKey, self::ORDER_CASH_TEMPLATES, true) => self::orderCashRoles($channel),
            in_array($workflowKey, self::ORDER_PAYMENT_TEMPLATES, true) => self::orderPaymentRoles($channel),
            in_array($workflowKey, self::DELIVERY_ASSIGNMENT_TEMPLATES, true) => self::deliveryAssignmentRoles($channel),
            in_array($workflowKey, self::DELIVERY_RESPONSE_TEMPLATES, true) => self::deliveryResponseRoles($channel),
            in_array($workflowKey, self::DELIVERY_STATUS_TEMPLATES, true) => self::deliveryStatusRoles($channel),
            in_array($workflowKey, self::EXTERNAL_COURIER_TEMPLATES, true) => self::externalCourierRoles($channel),
            in_array($workflowKey, self::DRIVER_TRACKING_TEMPLATES, true) => self::driverTrackingRoles($channel),
            in_array($workflowKey, self::DRIVER_SETTLEMENT_TEMPLATES, true) => self::driverSettlementRoles($channel),
            in_array($workflowKey, self::DRIVER_EARNING_TEMPLATES, true) => self::driverEarningRoles($channel),
            in_array($workflowKey, self::DRIVER_PAYOUT_ISSUE_TEMPLATES, true) => self::driverPayoutIssueRoles($channel),
            in_array($workflowKey, self::HR_PROFILE_TEMPLATES, true) => self::hrProfileRoles($channel),
            in_array($workflowKey, self::HR_ATTENDANCE_TEMPLATES, true) => self::hrAttendanceRoles($channel),
            in_array($workflowKey, self::HR_REQUEST_TEMPLATES, true) => self::hrRequestRoles($channel),
            in_array($workflowKey, self::PAYROLL_TEMPLATES, true) => self::payrollRoles(),
            in_array($workflowKey, self::EMPLOYEE_DOCUMENT_TEMPLATES, true) => self::employeeDocumentRoles(),
            in_array($workflowKey, self::POS_TEMPLATES, true) => self::posRoles(),
            in_array($workflowKey, self::TABLE_TEMPLATES, true) => self::tableRoles(),
            in_array($workflowKey, self::CUSTOMER_VALUE_TEMPLATES, true) => self::customerValueRoles($workflowKey, $channel),
            in_array($workflowKey, self::RESERVATION_TEMPLATES, true) => self::reservationRoles($channel),
            in_array($workflowKey, self::INVENTORY_TEMPLATES, true) => self::inventoryRoles(),
            in_array($workflowKey, self::EXPORT_TEMPLATES, true) => self::exportRoles(),
            default => [],
        }));
    }

    /**
     * Get roles for account-created notifications.
     *
     * @return array<int, string>
     */
    protected static function accountCreatedRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [
                DefaultRole::Customer->value,
                DefaultRole::Staff->value,
            ]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Staff->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Determine if the channel is customer-facing.
     */
    protected static function isExternalChannel(string $channel): bool
    {
        return in_array($channel, self::EXTERNAL_CHANNELS, true);
    }

    /**
     * Get branch admin roles.
     *
     * @return array<int, string>
     */
    protected static function branchAdminRoles(): array
    {
        return [
            DefaultRole::Manager->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Admin->value,
        ];
    }

    /**
     * Get order recipient roles.
     *
     * @return array<int, string>
     */
    protected static function orderRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Waiter->value,
                DefaultRole::Kitchen->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get order cash recipient roles.
     *
     * @return array<int, string>
     */
    protected static function orderCashRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Cashier->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get order payment recipient roles.
     *
     * @return array<int, string>
     */
    protected static function orderPaymentRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Cashier->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get delivery assignment recipient roles.
     *
     * @return array<int, string>
     */
    protected static function deliveryAssignmentRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value, DefaultRole::Driver->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Driver->value,
                DefaultRole::Dispatcher->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get delivery response recipient roles.
     *
     * @return array<int, string>
     */
    protected static function deliveryResponseRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Driver->value,
                DefaultRole::Dispatcher->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get delivery status recipient roles.
     *
     * @return array<int, string>
     */
    protected static function deliveryStatusRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Driver->value,
                DefaultRole::Dispatcher->value,
                DefaultRole::ExternalCourierOperator->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get external courier recipient roles.
     *
     * @return array<int, string>
     */
    protected static function externalCourierRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Dispatcher->value,
                DefaultRole::ExternalCourierOperator->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get driver tracking recipient roles.
     *
     * @return array<int, string>
     */
    protected static function driverTrackingRoles(string $channel): array
    {
        return $channel === 'sms'
            ? [DefaultRole::Driver->value]
            : [
                DefaultRole::Driver->value,
                DefaultRole::Dispatcher->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get driver settlement recipient roles.
     *
     * @return array<int, string>
     */
    protected static function driverSettlementRoles(string $channel): array
    {
        return $channel === 'sms'
            ? [DefaultRole::Driver->value]
            : [
                DefaultRole::Driver->value,
                DefaultRole::Cashier->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get driver earning recipient roles.
     *
     * @return array<int, string>
     */
    protected static function driverEarningRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Driver->value]
            : [
                DefaultRole::Driver->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get driver payout issue recipient roles.
     *
     * @return array<int, string>
     */
    protected static function driverPayoutIssueRoles(string $channel): array
    {
        return $channel === 'email'
            ? [DefaultRole::Driver->value]
            : [
                DefaultRole::Driver->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get HR profile recipient roles.
     *
     * @return array<int, string>
     */
    protected static function hrProfileRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Staff->value]
            : [
                DefaultRole::Staff->value,
                ...self::hrAdminRoles(),
            ];
    }

    /**
     * Get HR admin roles.
     *
     * @return array<int, string>
     */
    protected static function hrAdminRoles(): array
    {
        return [
            DefaultRole::HrManager->value,
            DefaultRole::HrOfficer->value,
            DefaultRole::Admin->value,
        ];
    }

    /**
     * Get attendance recipient roles.
     *
     * @return array<int, string>
     */
    protected static function hrAttendanceRoles(string $channel): array
    {
        return [
            DefaultRole::Staff->value,
            ...self::hrAdminRoles(),
        ];
    }

    /**
     * Get leave and overtime recipient roles.
     *
     * @return array<int, string>
     */
    protected static function hrRequestRoles(string $channel): array
    {
        return [
            DefaultRole::Staff->value,
            ...self::hrAdminRoles(),
        ];
    }

    /**
     * Get payroll recipient roles.
     *
     * @return array<int, string>
     */
    protected static function payrollRoles(): array
    {
        return [
            DefaultRole::Staff->value,
            DefaultRole::Payroll->value,
            ...self::hrAdminRoles(),
        ];
    }

    /**
     * Get employee document recipient roles.
     *
     * @return array<int, string>
     */
    protected static function employeeDocumentRoles(): array
    {
        return [
            DefaultRole::Staff->value,
            ...self::hrAdminRoles(),
        ];
    }

    /**
     * Get POS session recipient roles.
     *
     * @return array<int, string>
     */
    protected static function posRoles(): array
    {
        return [
            DefaultRole::Cashier->value,
            ...self::branchAdminRoles(),
        ];
    }

    /**
     * Get table recipient roles.
     *
     * @return array<int, string>
     */
    protected static function tableRoles(): array
    {
        return [
            DefaultRole::Waiter->value,
            ...self::branchAdminRoles(),
        ];
    }

    /**
     * Get gift card and loyalty recipient roles.
     *
     * @return array<int, string>
     */
    protected static function customerValueRoles(string $workflowKey, string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [DefaultRole::Customer->value, ...self::branchAdminRoles()];
    }

    /**
     * Get reservation recipient roles.
     *
     * @return array<int, string>
     */
    protected static function reservationRoles(string $channel): array
    {
        return self::isExternalChannel($channel)
            ? [DefaultRole::Customer->value]
            : [
                DefaultRole::Customer->value,
                DefaultRole::Waiter->value,
                DefaultRole::Cashier->value,
                ...self::branchAdminRoles(),
            ];
    }

    /**
     * Get inventory recipient roles.
     *
     * @return array<int, string>
     */
    protected static function inventoryRoles(): array
    {
        return [
            DefaultRole::Inventory->value,
            DefaultRole::Procurement->value,
            ...self::branchAdminRoles(),
        ];
    }

    /**
     * Get workflow keys that can target the driver app.
     *
     * @return array<int, string>
     */
    public static function driverAppWorkflowKeys(): array
    {
        return [
            ...self::DELIVERY_ASSIGNMENT_TEMPLATES,
            ...self::DELIVERY_RESPONSE_TEMPLATES,
            ...self::DELIVERY_STATUS_TEMPLATES,
            ...self::DRIVER_TRACKING_TEMPLATES,
            ...self::DRIVER_SETTLEMENT_TEMPLATES,
            ...self::DRIVER_EARNING_TEMPLATES,
            ...self::DRIVER_PAYOUT_ISSUE_TEMPLATES,
        ];
    }

    /**
     * Get workflow keys that can target the employee app.
     *
     * @return array<int, string>
     */
    public static function employeeAppWorkflowKeys(): array
    {
        return [
            ...self::HR_PROFILE_TEMPLATES,
            ...self::HR_ATTENDANCE_TEMPLATES,
            ...self::HR_REQUEST_TEMPLATES,
            ...self::PAYROLL_TEMPLATES,
            ...self::EMPLOYEE_DOCUMENT_TEMPLATES,
        ];
    }

    /**
     * Get account workflow keys that can target linked employee accounts.
     *
     * @return array<int, string>
     */
    public static function accountWorkflowKeys(): array
    {
        return self::ACCOUNT_CREATED_TEMPLATES;
    }
}
