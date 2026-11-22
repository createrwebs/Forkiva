<?php

namespace Modules\Notification\Database\Seeders\NotificationTemplates;

use InvalidArgumentException;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverService;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\User\Enums\DefaultRole;

class NotificationRequirementTemplateCatalog
{
    private const CHANNELS = [
        'profile_updated' => ['email', 'in_app'],
        'new_device_login_detected' => ['email', 'in_app', 'push_notification'],
        'order_created' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'order_updated' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'order_cancelled' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'order_refunded' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'order_voided' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'order_payment_completed' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'order_status_changed' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'delivery_assigned' => ['in_app', 'push_notification', 'sms', 'whatsapp'],
        'delivery_reassigned' => ['in_app', 'push_notification', 'sms', 'whatsapp'],
        'driver_delivery_accepted' => ['in_app', 'push_notification', 'sms', 'whatsapp'],
        'delivery_status_changed' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'external_courier_dispatch_updated' => ['in_app', 'push_notification', 'sms', 'whatsapp'],
        'driver_tracking_offline' => ['in_app', 'push_notification', 'sms'],
        'driver_tracking_permission_issue' => ['in_app', 'push_notification', 'sms'],
        'driver_tracking_token_issue' => ['in_app', 'push_notification', 'sms'],
        'driver_cash_settlement_updated' => ['in_app', 'push_notification', 'sms', 'whatsapp'],
        'driver_earnings_updated' => ['email', 'in_app', 'push_notification', 'whatsapp'],
        'driver_delivery_rejected' => ['in_app', 'push_notification'],
        'driver_batch_accepted' => ['in_app', 'push_notification'],
        'driver_tracking_stale' => ['in_app', 'push_notification'],
        'driver_cod_declared' => ['in_app', 'push_notification'],
        'driver_payout_updated' => ['email', 'in_app', 'push_notification', 'sms'],
        'driver_payout_issue_reported' => ['in_app', 'push_notification'],
        'driver_payout_issue_resolved' => ['email', 'in_app', 'push_notification'],
        'pos_cash_variance_found' => ['email', 'in_app', 'push_notification'],
        'pos_session_opened' => ['in_app', 'push_notification'],
        'pos_session_closed' => ['email', 'in_app', 'push_notification'],
        'stock_movement_needs_attention' => ['email', 'in_app', 'push_notification'],
        'stock_transfer_status_changed' => ['email', 'in_app', 'push_notification'],
        'kitchen_item_ready' => ['in_app', 'push_notification'],
        'account_created' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'suspicious_login_detected' => ['email', 'in_app', 'push_notification', 'sms'],
        'account_activated' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'password_changed' => ['email', 'in_app', 'push_notification', 'sms'],
        'employment_profile_updated' => ['email', 'in_app', 'push_notification'],
        'employee_document_available' => ['email', 'in_app', 'push_notification'],
        'employee_created' => ['email', 'in_app', 'push_notification', 'sms'],
        'attendance_event_recorded' => ['in_app', 'push_notification'],
        'attendance_manual_adjusted' => ['email', 'in_app', 'push_notification'],
        'attendance_record_lock_changed' => ['email', 'in_app', 'push_notification'],
        'leave_request_submitted' => ['email', 'in_app', 'push_notification'],
        'leave_request_approved' => ['email', 'in_app', 'push_notification', 'sms'],
        'leave_request_rejected' => ['email', 'in_app', 'push_notification', 'sms'],
        'leave_request_cancelled' => ['email', 'in_app', 'push_notification'],
        'overtime_request_submitted' => ['in_app', 'push_notification'],
        'overtime_request_approved' => ['email', 'in_app', 'push_notification', 'sms'],
        'overtime_request_rejected' => ['email', 'in_app', 'push_notification', 'sms'],
        'overtime_request_cancelled' => ['email', 'in_app', 'push_notification'],
        'payroll_run_updated' => ['email', 'in_app', 'push_notification'],
        'payslip_updated' => ['email', 'in_app', 'push_notification'],
        'payroll_payment_updated' => ['email', 'in_app', 'push_notification'],
        'payslip_file_available' => ['email', 'in_app', 'push_notification'],
        'table_assigned_to_waiter' => ['in_app', 'push_notification'],
        'table_status_changed' => ['in_app', 'push_notification'],
        'loyalty_points_changed' => ['email', 'in_app', 'push_notification'],
        'loyalty_reward_redeemed' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'loyalty_tier_changed' => ['email', 'in_app', 'push_notification'],
        'export_completed' => ['email', 'in_app', 'push_notification'],
        'export_failed' => ['email', 'in_app', 'push_notification'],
        'inventory_purchase_updated' => ['email', 'in_app', 'push_notification'],
        'gift_card_issued' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'gift_card_redeemed' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'gift_card_recharged' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'gift_card_adjusted' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'gift_card_expired' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_created' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_updated' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_confirmed' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_cancelled' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_arrived' => ['in_app', 'push_notification'],
        'reservation_seated' => ['in_app', 'push_notification'],
        'reservation_completed' => ['email', 'in_app', 'push_notification'],
        'reservation_no_show' => ['email', 'in_app', 'push_notification', 'sms'],
        'reservation_expired' => ['email', 'in_app', 'push_notification'],
        'reservation_rejected' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_table_assigned' => ['in_app', 'push_notification'],
        'reservation_reminder' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_waitlist_notified' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
        'reservation_waitlist_converted' => ['email', 'in_app', 'push_notification', 'sms', 'whatsapp'],
    ];

    /**
     * Get one notification requirement template definition.
     *
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown notification template key [$key].");
    }

    /**
     * Get all notification requirement template definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $templates = [
            'account_created' => [
                'key' => 'account_created',
                'legacy_keys' => [
                    'customer_created_welcome',
                ],
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'تم إنشاء الحساب',
                ],
                'description' => [
                    'en' => 'Sent when an account is created.',
                    'ar' => 'يُرسل عند إنشاء الحساب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'account_email',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Welcome to {{ app_name }}',
                            'ar' => 'مرحباً بك في {{ app_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Your {{ app_name }} account has been created successfully for {{ account_email }}. You can now sign in and start using the available features.</p><p>If you did not request this account, please contact support immediately.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إنشاء حسابك في {{ app_name }} بنجاح باستخدام البريد {{ account_email }}. يمكنك الآن تسجيل الدخول والبدء باستخدام الميزات المتاحة.</p><p>إذا لم تطلب إنشاء هذا الحساب، يرجى التواصل مع الدعم فوراً.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Your account for {{ account_email }} has been created successfully.',
                            'ar' => 'تم إنشاء حسابك للبريد {{ account_email }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Your {{ app_name }} account is ready.',
                            'ar' => 'حسابك في {{ app_name }} جاهز الآن.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Your account for {{ account_email }} has been created successfully.',
                            'ar' => '{{ app_name }}: تم إنشاء حسابك للبريد {{ account_email }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, your {{ app_name }} account has been created for {{ account_email }}. You can now sign in and start using the service.',
                            'ar' => 'مرحباً {{ user_name }}، تم إنشاء حسابك في {{ app_name }} للبريد {{ account_email }}. يمكنك الآن تسجيل الدخول والبدء باستخدام الخدمة.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'account_activated' => [
                'key' => 'account_activated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Account Activated',
                    'ar' => 'تم تفعيل الحساب',
                ],
                'description' => [
                    'en' => 'Sent when an account is activated.',
                    'ar' => 'يُرسل عند تفعيل الحساب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'activation_date',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Your account is now active',
                            'ar' => 'تم تفعيل حسابك',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Your account has been activated successfully on {{ activation_date }}. You now have access to the available features in {{ app_name }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تفعيل حسابك بنجاح بتاريخ {{ activation_date }}. أصبح بإمكانك الآن الوصول إلى الميزات المتاحة في {{ app_name }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Your account has been activated successfully.',
                            'ar' => 'تم تفعيل حسابك بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Your account has been activated successfully.',
                            'ar' => 'تم تفعيل حسابك بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Your account has been activated successfully. - {{ app_name }}',
                            'ar' => 'تم تفعيل حسابك بنجاح. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, your {{ app_name }} account was activated on {{ activation_date }}. You can now access the available features.',
                            'ar' => 'مرحباً {{ user_name }}، تم تفعيل حسابك في {{ app_name }} بتاريخ {{ activation_date }}. يمكنك الآن الوصول إلى الميزات المتاحة.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'profile_updated' => [
                'key' => 'profile_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Profile Updated',
                    'ar' => 'تحديث الملف الشخصي',
                ],
                'description' => [
                    'en' => 'Sent when a profile is updated.',
                    'ar' => 'يُرسل عند تحديث الملف الشخصي.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'updated_at',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Your profile was updated',
                            'ar' => 'تم تحديث ملفك الشخصي',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Your profile information in {{ app_name }} was updated on {{ updated_at }}.</p><p>No action is required if you made this change. If you did not, please review your account activity.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث معلومات ملفك الشخصي في {{ app_name }} بتاريخ {{ updated_at }}.</p><p>لا يلزم اتخاذ أي إجراء إذا كنت أنت من قام بهذا التحديث. إذا لم تكن أنت، يرجى مراجعة نشاط حسابك.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Your profile information was updated.',
                            'ar' => 'تم تحديث معلومات ملفك الشخصي.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Your profile information was updated.',
                            'ar' => 'تم تحديث معلومات ملفك الشخصي.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Your profile information was updated. - {{ app_name }}',
                            'ar' => 'تم تحديث معلومات ملفك الشخصي. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'password_changed' => [
                'key' => 'password_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Password Changed',
                    'ar' => 'تغيير كلمة المرور',
                ],
                'description' => [
                    'en' => 'Sent when a password is changed.',
                    'ar' => 'يُرسل عند تغيير كلمة المرور.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'changed_at',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Your password was changed',
                            'ar' => 'تم تغيير كلمة المرور',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Your {{ app_name }} password was changed on {{ changed_at }}.</p><p>If this was not you, reset your password immediately and review active sessions on your account.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تغيير كلمة المرور الخاصة بحسابك في {{ app_name }} بتاريخ {{ changed_at }}.</p><p>إذا لم تكن أنت من قام بهذا التغيير، يرجى إعادة تعيين كلمة المرور فوراً ومراجعة جلسات الدخول النشطة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Your password was changed on {{ changed_at }}. Secure your account if this was not you.',
                            'ar' => 'تم تغيير كلمة المرور بتاريخ {{ changed_at }}. قم بتأمين حسابك إذا لم تكن أنت من قام بذلك.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Password changed. Secure your account if this was not you.',
                            'ar' => 'تم تغيير كلمة المرور. قم بتأمين حسابك إذا لم تكن أنت من قام بذلك.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Your password was changed on {{ changed_at }}. If this was not you, secure your account immediately.',
                            'ar' => '{{ app_name }}: تم تغيير كلمة المرور بتاريخ {{ changed_at }}. إذا لم تكن أنت، قم بتأمين حسابك فوراً.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'new_device_login_detected' => [
                'key' => 'new_device_login_detected',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'New Device Login Detected',
                    'ar' => 'اكتشاف تسجيل دخول من جهاز جديد',
                ],
                'description' => [
                    'en' => 'Sent when a login is detected from a new device.',
                    'ar' => 'يُرسل عند اكتشاف تسجيل دخول من جهاز جديد.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'device_name',
                    'login_time',
                    'location',
                    'ip_address',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'New sign-in detected on your account',
                            'ar' => 'تم اكتشاف تسجيل دخول من جهاز جديد',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>A new sign-in to your {{ app_name }} account was detected from {{ device_name }} at {{ login_time }}.</p><p><strong>Location:</strong> {{ location }}<br><strong>IP address:</strong> {{ ip_address }}</p><p>If this was not you, change your password immediately and review active sessions.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم اكتشاف تسجيل دخول جديد إلى حسابك في {{ app_name }} من الجهاز {{ device_name }} في {{ login_time }}.</p><p><strong>الموقع:</strong> {{ location }}<br><strong>عنوان IP:</strong> {{ ip_address }}</p><p>إذا لم تكن أنت، يرجى تغيير كلمة المرور فوراً ومراجعة جلسات الدخول النشطة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'New sign-in from {{ device_name }} at {{ login_time }}. Location: {{ location }}.',
                            'ar' => 'تسجيل دخول جديد من {{ device_name }} في {{ login_time }}. الموقع: {{ location }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'New sign-in detected from {{ device_name }}.',
                            'ar' => 'تم اكتشاف تسجيل دخول جديد من {{ device_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: New sign-in from {{ device_name }} at {{ login_time }}. IP: {{ ip_address }}.',
                            'ar' => '{{ app_name }}: تسجيل دخول جديد من {{ device_name }} في {{ login_time }}. IP: {{ ip_address }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'suspicious_login_detected' => [
                'key' => 'suspicious_login_detected',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Suspicious Login Detected',
                    'ar' => 'اكتشاف تسجيل دخول مشبوه',
                ],
                'description' => [
                    'en' => 'Sent when a suspicious login is detected.',
                    'ar' => 'يُرسل عند اكتشاف تسجيل دخول مشبوه.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'login_time',
                    'location',
                    'ip_address',
                    'device_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Suspicious sign-in attempt detected',
                            'ar' => 'تم اكتشاف محاولة تسجيل دخول مشبوهة',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>We detected a suspicious sign-in attempt on your {{ app_name }} account from {{ device_name }} at {{ login_time }}.</p><p><strong>Location:</strong> {{ location }}<br><strong>IP address:</strong> {{ ip_address }}</p><p>If this was not you, change your password immediately, review active sessions, and contact support if needed.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم اكتشاف محاولة تسجيل دخول مشبوهة إلى حسابك في {{ app_name }} من الجهاز {{ device_name }} في {{ login_time }}.</p><p><strong>الموقع:</strong> {{ location }}<br><strong>عنوان IP:</strong> {{ ip_address }}</p><p>إذا لم تكن أنت، يرجى تغيير كلمة المرور فوراً ومراجعة جلسات الدخول النشطة والتواصل مع الدعم عند الحاجة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Suspicious sign-in attempt from {{ device_name }} at {{ login_time }}. Review your account security now.',
                            'ar' => 'محاولة تسجيل دخول مشبوهة من {{ device_name }} في {{ login_time }}. راجع أمان حسابك الآن.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Suspicious sign-in attempt detected. Secure your account now.',
                            'ar' => 'تم اكتشاف محاولة دخول مشبوهة. قم بتأمين حسابك الآن.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Suspicious sign-in from {{ device_name }} at {{ login_time }}. If this was not you, secure your account immediately.',
                            'ar' => '{{ app_name }}: محاولة دخول مشبوهة من {{ device_name }} في {{ login_time }}. إذا لم تكن أنت، قم بتأمين حسابك فوراً.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_created' => [
                'key' => 'order_created',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Created',
                    'ar' => 'إنشاء الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order is created.',
                    'ar' => 'يُرسل عند إنشاء الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'branch_name',
                    'order_status',
                    'order_total',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Order {{ order_number }} created',
                            'ar' => 'تم إنشاء الطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Order {{ order_number }} has been created successfully at {{ branch_name }}. Current status: {{ order_status }}. Total: {{ order_total }} {{ currency }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إنشاء الطلب {{ order_number }} بنجاح في {{ branch_name }}. الحالة الحالية: {{ order_status }}. الإجمالي: {{ order_total }} {{ currency }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Order {{ order_number }} has been created successfully.',
                            'ar' => 'تم إنشاء الطلب {{ order_number }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Order {{ order_number }} has been created successfully.',
                            'ar' => 'تم إنشاء الطلب {{ order_number }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Order {{ order_number }} has been created successfully. - {{ app_name }}',
                            'ar' => 'تم إنشاء الطلب {{ order_number }} بنجاح. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, order {{ order_number }} was created at {{ branch_name }}. Status: {{ order_status }}. Total: {{ order_total }} {{ currency }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم إنشاء الطلب {{ order_number }} في {{ branch_name }}. الحالة: {{ order_status }}. الإجمالي: {{ order_total }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_updated' => [
                'key' => 'order_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Updated',
                    'ar' => 'تحديث الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order is updated.',
                    'ar' => 'يُرسل عند تحديث الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'order_status',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Order {{ order_number }} updated',
                            'ar' => 'تم تحديث الطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Order {{ order_number }} at {{ branch_name }} has been updated. Latest status: {{ order_status }}.</p><p>Please review the order details if any action is required.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث الطلب {{ order_number }} في {{ branch_name }}. الحالة الأخيرة: {{ order_status }}.</p><p>يرجى مراجعة تفاصيل الطلب عند الحاجة إلى اتخاذ إجراء.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Order {{ order_number }} at {{ branch_name }} was updated to {{ order_status }}.',
                            'ar' => 'تم تحديث الطلب {{ order_number }} في {{ branch_name }} إلى {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Order {{ order_number }} updated: {{ order_status }}.',
                            'ar' => 'تم تحديث الطلب {{ order_number }}: {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Order {{ order_number }} at {{ branch_name }} was updated to {{ order_status }}.',
                            'ar' => '{{ app_name }}: تم تحديث الطلب {{ order_number }} في {{ branch_name }} إلى {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, order {{ order_number }} at {{ branch_name }} was updated. Current status: {{ order_status }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم تحديث الطلب {{ order_number }} في {{ branch_name }}. الحالة الحالية: {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_status_changed' => [
                'key' => 'order_status_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Status Changed',
                    'ar' => 'تغيير حالة الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order status changes.',
                    'ar' => 'يُرسل عند تغيير حالة الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'order_status',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Order {{ order_number }} status changed',
                            'ar' => 'تم تغيير حالة الطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The status of order {{ order_number }} at {{ branch_name }} has changed to {{ order_status }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث حالة الطلب {{ order_number }} في {{ branch_name }} إلى {{ order_status }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Order {{ order_number }} at {{ branch_name }} is now {{ order_status }}.',
                            'ar' => 'أصبحت حالة الطلب {{ order_number }} في {{ branch_name }} الآن {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Order {{ order_number }} is now {{ order_status }}.',
                            'ar' => 'أصبح الطلب {{ order_number }} الآن {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Order {{ order_number }} at {{ branch_name }} is now {{ order_status }}.',
                            'ar' => '{{ app_name }}: أصبحت حالة الطلب {{ order_number }} في {{ branch_name }} الآن {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, order {{ order_number }} at {{ branch_name }} is now {{ order_status }}.',
                            'ar' => 'مرحباً {{ user_name }}، أصبحت حالة الطلب {{ order_number }} في {{ branch_name }} الآن {{ order_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'kitchen_item_ready' => [
                'key' => 'kitchen_item_ready',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Kitchen Item Ready',
                    'ar' => 'جاهزية عنصر المطبخ',
                ],
                'description' => [
                    'en' => 'Sent when a kitchen item becomes ready.',
                    'ar' => 'يُرسل عند جاهزية عنصر المطبخ.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'item_name',
                    'kitchen_station',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Kitchen item ready for order {{ order_number }}',
                            'ar' => 'عنصر المطبخ جاهز للطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The kitchen item {{ item_name }} for order {{ order_number }} is ready at {{ kitchen_station }} in {{ branch_name }}.</p><p>Please continue the service workflow as required.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>أصبح عنصر المطبخ {{ item_name }} للطلب {{ order_number }} جاهزاً في {{ kitchen_station }} ضمن {{ branch_name }}.</p><p>يرجى متابعة سير الخدمة حسب الإجراء المطلوب.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, {{ item_name }} is ready for order {{ order_number }} at {{ kitchen_station }} - {{ branch_name }} in {{ app_name }}.',
                            'ar' => 'مرحباً {{ user_name }}، العنصر {{ item_name }} جاهز للطلب {{ order_number }} في {{ kitchen_station }} - {{ branch_name }} ضمن {{ app_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ item_name }} ready for order {{ order_number }}.',
                            'ar' => '{{ item_name }} جاهز للطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ item_name }} is ready for order {{ order_number }} at {{ kitchen_station }}.',
                            'ar' => '{{ app_name }}: {{ item_name }} جاهز للطلب {{ order_number }} في {{ kitchen_station }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ item_name }} is ready for order {{ order_number }} at {{ kitchen_station }} in {{ branch_name }}.',
                            'ar' => 'مرحباً {{ user_name }}، العنصر {{ item_name }} جاهز للطلب {{ order_number }} في {{ kitchen_station }} ضمن {{ branch_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_cancelled' => [
                'key' => 'order_cancelled',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Cancelled',
                    'ar' => 'إلغاء الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order is cancelled.',
                    'ar' => 'يُرسل عند إلغاء الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'branch_name',
                    'cancellation_reason',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Order {{ order_number }} cancelled',
                            'ar' => 'تم إلغاء الطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Order {{ order_number }} at {{ branch_name }} has been cancelled.</p><p><strong>Reason:</strong> {{ cancellation_reason }}</p><p>Please review the order details if follow-up is required.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إلغاء الطلب {{ order_number }} في {{ branch_name }}.</p><p><strong>السبب:</strong> {{ cancellation_reason }}</p><p>يرجى مراجعة تفاصيل الطلب إذا كانت هناك حاجة إلى متابعة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Order {{ order_number }} at {{ branch_name }} was cancelled. Reason: {{ cancellation_reason }}.',
                            'ar' => 'تم إلغاء الطلب {{ order_number }} في {{ branch_name }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Order {{ order_number }} was cancelled.',
                            'ar' => 'تم إلغاء الطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Order {{ order_number }} at {{ branch_name }} was cancelled. Reason: {{ cancellation_reason }}.',
                            'ar' => '{{ app_name }}: تم إلغاء الطلب {{ order_number }} في {{ branch_name }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, order {{ order_number }} at {{ branch_name }} was cancelled. Reason: {{ cancellation_reason }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم إلغاء الطلب {{ order_number }} في {{ branch_name }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_refunded' => [
                'key' => 'order_refunded',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Refunded',
                    'ar' => 'استرداد الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order is refunded.',
                    'ar' => 'يُرسل عند استرداد الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'refund_amount',
                    'currency',
                    'refund_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Refund processed for order {{ order_number }}',
                            'ar' => 'تم تنفيذ استرداد للطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>A refund has been processed for order {{ order_number }}. Refund amount: {{ refund_amount }} {{ currency }}. Refund status: {{ refund_status }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تنفيذ عملية استرداد للطلب {{ order_number }}. مبلغ الاسترداد: {{ refund_amount }} {{ currency }}. حالة الاسترداد: {{ refund_status }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Refund processed for order {{ order_number }}.',
                            'ar' => 'تم تنفيذ استرداد للطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Refund processed for order {{ order_number }}.',
                            'ar' => 'تم تنفيذ استرداد للطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Refund processed for order {{ order_number }}. - {{ app_name }}',
                            'ar' => 'تم تنفيذ استرداد للطلب {{ order_number }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, refund for order {{ order_number }} was processed. Amount: {{ refund_amount }} {{ currency }}. Status: {{ refund_status }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم تنفيذ استرداد للطلب {{ order_number }}. المبلغ: {{ refund_amount }} {{ currency }}. الحالة: {{ refund_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_voided' => [
                'key' => 'order_voided',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Voided',
                    'ar' => 'إبطال الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order is voided.',
                    'ar' => 'يُرسل عند إبطال الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'void_reason',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Order {{ order_number }} voided',
                            'ar' => 'تم إبطال الطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Order {{ order_number }} at {{ branch_name }} has been voided and will no longer be processed.</p><p><strong>Reason:</strong> {{ void_reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إبطال الطلب {{ order_number }} في {{ branch_name }} ولن تتم معالجته بعد الآن.</p><p><strong>السبب:</strong> {{ void_reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Order {{ order_number }} at {{ branch_name }} was voided. Reason: {{ void_reason }}.',
                            'ar' => 'تم إبطال الطلب {{ order_number }} في {{ branch_name }}. السبب: {{ void_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Order {{ order_number }} was voided.',
                            'ar' => 'تم إبطال الطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Order {{ order_number }} at {{ branch_name }} was voided. Reason: {{ void_reason }}.',
                            'ar' => '{{ app_name }}: تم إبطال الطلب {{ order_number }} في {{ branch_name }}. السبب: {{ void_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, order {{ order_number }} at {{ branch_name }} was voided. Reason: {{ void_reason }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم إبطال الطلب {{ order_number }} في {{ branch_name }}. السبب: {{ void_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'order_payment_completed' => [
                'key' => 'order_payment_completed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Order Payment Completed',
                    'ar' => 'اكتمال دفع الطلب',
                ],
                'description' => [
                    'en' => 'Sent when an order payment is completed.',
                    'ar' => 'يُرسل عند اكتمال دفع الطلب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'paid_amount',
                    'currency',
                    'payment_method',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payment completed for order {{ order_number }}',
                            'ar' => 'اكتمل دفع الطلب {{ order_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Payment for order {{ order_number }} has been completed successfully. Paid amount: {{ paid_amount }} {{ currency }}. Payment method: {{ payment_method }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إكمال دفع الطلب {{ order_number }} بنجاح. المبلغ المدفوع: {{ paid_amount }} {{ currency }}. طريقة الدفع: {{ payment_method }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payment completed for order {{ order_number }}.',
                            'ar' => 'اكتمل دفع الطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payment completed for order {{ order_number }}.',
                            'ar' => 'اكتمل دفع الطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Payment completed for order {{ order_number }}. - {{ app_name }}',
                            'ar' => 'اكتمل دفع الطلب {{ order_number }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, payment for order {{ order_number }} was completed. Amount: {{ paid_amount }} {{ currency }}. Method: {{ payment_method }}.',
                            'ar' => 'مرحباً {{ user_name }}، اكتمل دفع الطلب {{ order_number }}. المبلغ: {{ paid_amount }} {{ currency }}. طريقة الدفع: {{ payment_method }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'delivery_assigned' => [
                'key' => 'delivery_assigned',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Delivery Assigned',
                    'ar' => 'تعيين التوصيل',
                ],
                'description' => [
                    'en' => 'Sent when a delivery is assigned.',
                    'ar' => 'يُرسل عند تعيين التوصيل.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'delivery_number',
                    'order_number',
                    'driver_name',
                    'eta',
                    'pickup_address',
                    'delivery_address',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Delivery {{ delivery_number }} assigned',
                            'ar' => 'تم تعيين التوصيل {{ delivery_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} has been assigned to {{ driver_name }}.</p><p><strong>ETA:</strong> {{ eta }}<br><strong>Pickup:</strong> {{ pickup_address }}<br><strong>Delivery:</strong> {{ delivery_address }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}<br><strong>الاستلام:</strong> {{ pickup_address }}<br><strong>التسليم:</strong> {{ delivery_address }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} for order {{ order_number }} assigned to {{ driver_name }}. ETA: {{ eta }}.',
                            'ar' => 'تم تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}. وقت الوصول المتوقع: {{ eta }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} assigned to {{ driver_name }}. ETA: {{ eta }}.',
                            'ar' => 'تم تعيين التوصيل {{ delivery_number }} إلى {{ driver_name }}. ETA: {{ eta }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Delivery {{ delivery_number }} for order {{ order_number }} assigned to {{ driver_name }}. ETA: {{ eta }}.',
                            'ar' => '{{ app_name }}: تم تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}. ETA: {{ eta }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, delivery {{ delivery_number }} for order {{ order_number }} was assigned to {{ driver_name }}. ETA: {{ eta }}. Pickup: {{ pickup_address }}. Delivery: {{ delivery_address }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}. ETA: {{ eta }}. الاستلام: {{ pickup_address }}. التسليم: {{ delivery_address }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'delivery_reassigned' => [
                'key' => 'delivery_reassigned',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Delivery Reassigned',
                    'ar' => 'إعادة تعيين التوصيل',
                ],
                'description' => [
                    'en' => 'Sent when a delivery is reassigned.',
                    'ar' => 'يُرسل عند إعادة تعيين التوصيل.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'delivery_number',
                    'order_number',
                    'driver_name',
                    'eta',
                    'reassignment_reason',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Delivery {{ delivery_number }} reassigned',
                            'ar' => 'تمت إعادة تعيين التوصيل {{ delivery_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} has been reassigned to {{ driver_name }}.</p><p><strong>ETA:</strong> {{ eta }}<br><strong>Reason:</strong> {{ reassignment_reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تمت إعادة تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}<br><strong>السبب:</strong> {{ reassignment_reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} for order {{ order_number }} reassigned to {{ driver_name }}. Reason: {{ reassignment_reason }}.',
                            'ar' => 'تمت إعادة تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}. السبب: {{ reassignment_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} reassigned to {{ driver_name }}.',
                            'ar' => 'تمت إعادة تعيين التوصيل {{ delivery_number }} إلى {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Delivery {{ delivery_number }} reassigned to {{ driver_name }}. ETA: {{ eta }}.',
                            'ar' => '{{ app_name }}: تمت إعادة تعيين التوصيل {{ delivery_number }} إلى {{ driver_name }}. ETA: {{ eta }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, delivery {{ delivery_number }} for order {{ order_number }} was reassigned to {{ driver_name }}. ETA: {{ eta }}. Reason: {{ reassignment_reason }}.',
                            'ar' => 'مرحباً {{ user_name }}، تمت إعادة تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}. ETA: {{ eta }}. السبب: {{ reassignment_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_delivery_accepted' => [
                'key' => 'driver_delivery_accepted',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Delivery Accepted',
                    'ar' => 'قبول السائق للتوصيل',
                ],
                'description' => [
                    'en' => 'Sent when a driver accepts a delivery.',
                    'ar' => 'يُرسل عند قبول السائق للتوصيل.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'delivery_number',
                    'order_number',
                    'driver_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Delivery {{ delivery_number }} accepted',
                            'ar' => 'تم قبول التوصيل {{ delivery_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Driver {{ driver_name }} has accepted delivery {{ delivery_number }} for order {{ order_number }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>قبل السائق {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Driver {{ driver_name }} accepted delivery {{ delivery_number }} for order {{ order_number }}.',
                            'ar' => 'قبل السائق {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ driver_name }} accepted delivery {{ delivery_number }}.',
                            'ar' => 'قبل {{ driver_name }} التوصيل {{ delivery_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ driver_name }} accepted delivery {{ delivery_number }} for order {{ order_number }}.',
                            'ar' => '{{ app_name }}: قبل {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ driver_name }} accepted delivery {{ delivery_number }} for order {{ order_number }}.',
                            'ar' => 'مرحباً {{ user_name }}، قبل {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_delivery_rejected' => [
                'key' => 'driver_delivery_rejected',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Delivery Rejected',
                    'ar' => 'رفض السائق للتوصيل',
                ],
                'description' => [
                    'en' => 'Sent when a driver rejects a delivery.',
                    'ar' => 'يُرسل عند رفض السائق للتوصيل.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'delivery_number',
                    'order_number',
                    'driver_name',
                    'rejection_reason',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Delivery {{ delivery_number }} rejected',
                            'ar' => 'تم رفض التوصيل {{ delivery_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Driver {{ driver_name }} rejected delivery {{ delivery_number }} for order {{ order_number }}.</p><p><strong>Reason:</strong> {{ rejection_reason }}</p><p>Please review and assign another driver if needed.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>رفض السائق {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.</p><p><strong>السبب:</strong> {{ rejection_reason }}</p><p>يرجى المراجعة وتعيين سائق آخر إذا لزم الأمر.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, driver {{ driver_name }} rejected delivery {{ delivery_number }} for order {{ order_number }}. Reason: {{ rejection_reason }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، رفض السائق {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}. السبب: {{ rejection_reason }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ driver_name }} rejected delivery {{ delivery_number }}.',
                            'ar' => 'رفض {{ driver_name }} التوصيل {{ delivery_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ driver_name }} rejected delivery {{ delivery_number }} for order {{ order_number }}. Reason: {{ rejection_reason }}.',
                            'ar' => '{{ app_name }}: رفض {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}. السبب: {{ rejection_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ driver_name }} rejected delivery {{ delivery_number }} for order {{ order_number }}. Reason: {{ rejection_reason }}.',
                            'ar' => 'مرحباً {{ user_name }}، رفض {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}. السبب: {{ rejection_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_batch_accepted' => [
                'key' => 'driver_batch_accepted',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Batch Accepted',
                    'ar' => 'قبول السائق للدفعة',
                ],
                'description' => [
                    'en' => 'Sent when a driver accepts a batch.',
                    'ar' => 'يُرسل عند قبول السائق للدفعة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'batch_number',
                    'driver_name',
                    'delivery_count',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Batch {{ batch_number }} accepted',
                            'ar' => 'تم قبول الدفعة {{ batch_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Driver {{ driver_name }} has accepted batch {{ batch_number }} with {{ delivery_count }} deliveries.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>قبل السائق {{ driver_name }} الدفعة {{ batch_number }} التي تحتوي على {{ delivery_count }} عمليات توصيل.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, driver {{ driver_name }} accepted batch {{ batch_number }} with {{ delivery_count }} deliveries. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، قبل السائق {{ driver_name }} الدفعة {{ batch_number }} التي تحتوي على {{ delivery_count }} عمليات توصيل. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ driver_name }} accepted batch {{ batch_number }}: {{ delivery_count }} deliveries.',
                            'ar' => 'قبل {{ driver_name }} الدفعة {{ batch_number }}: {{ delivery_count }} عمليات توصيل.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ driver_name }} accepted batch {{ batch_number }} with {{ delivery_count }} deliveries.',
                            'ar' => '{{ app_name }}: قبل {{ driver_name }} الدفعة {{ batch_number }} وفيها {{ delivery_count }} عمليات توصيل.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ driver_name }} accepted batch {{ batch_number }} with {{ delivery_count }} deliveries.',
                            'ar' => 'مرحباً {{ user_name }}، قبل {{ driver_name }} الدفعة {{ batch_number }} التي تحتوي على {{ delivery_count }} عمليات توصيل.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'delivery_status_changed' => [
                'key' => 'delivery_status_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Delivery Status Changed',
                    'ar' => 'تغيير حالة التوصيل',
                ],
                'description' => [
                    'en' => 'Sent when a delivery status changes.',
                    'ar' => 'يُرسل عند تغيير حالة التوصيل.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'delivery_number',
                    'order_number',
                    'delivery_status',
                    'driver_name',
                    'eta',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Delivery {{ delivery_number }} status changed',
                            'ar' => 'تم تغيير حالة التوصيل {{ delivery_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} is now {{ delivery_status }}. Driver: {{ driver_name }}. Estimated arrival time: {{ eta }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>أصبحت حالة التوصيل {{ delivery_number }} للطلب {{ order_number }} الآن {{ delivery_status }}. السائق: {{ driver_name }}. وقت الوصول المتوقع: {{ eta }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} status is now {{ delivery_status }}.',
                            'ar' => 'أصبحت حالة التوصيل {{ delivery_number }} الآن {{ delivery_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} status is now {{ delivery_status }}.',
                            'ar' => 'أصبحت حالة التوصيل {{ delivery_number }} الآن {{ delivery_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Delivery {{ delivery_number }} status is now {{ delivery_status }}. - {{ app_name }}',
                            'ar' => 'أصبحت حالة التوصيل {{ delivery_number }} الآن {{ delivery_status }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, delivery {{ delivery_number }} for order {{ order_number }} is now {{ delivery_status }}. Driver: {{ driver_name }}. ETA: {{ eta }}.',
                            'ar' => 'مرحباً {{ user_name }}، أصبحت حالة التوصيل {{ delivery_number }} للطلب {{ order_number }} الآن {{ delivery_status }}. السائق: {{ driver_name }}. ETA: {{ eta }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'external_courier_dispatch_updated' => [
                'key' => 'external_courier_dispatch_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'External Courier Dispatch Updated',
                    'ar' => 'تحديث إرسال شركة التوصيل الخارجية',
                ],
                'description' => [
                    'en' => 'Sent when an external courier dispatch is updated.',
                    'ar' => 'يُرسل عند تحديث إرسال شركة التوصيل الخارجية.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'order_number',
                    'courier_name',
                    'dispatch_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'External courier dispatch updated',
                            'ar' => 'تم تحديث إرسال شركة التوصيل الخارجية',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The external courier dispatch for order {{ order_number }} has been updated.</p><p><strong>Courier:</strong> {{ courier_name }}<br><strong>Status:</strong> {{ dispatch_status }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث إرسال شركة التوصيل الخارجية للطلب {{ order_number }}.</p><p><strong>شركة التوصيل:</strong> {{ courier_name }}<br><strong>الحالة:</strong> {{ dispatch_status }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'External courier {{ courier_name }} for order {{ order_number }} is {{ dispatch_status }}.',
                            'ar' => 'أصبحت حالة شركة التوصيل {{ courier_name }} للطلب {{ order_number }}: {{ dispatch_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ courier_name }} dispatch for order {{ order_number }} is {{ dispatch_status }}.',
                            'ar' => 'إرسال {{ courier_name }} للطلب {{ order_number }}: {{ dispatch_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ courier_name }} dispatch for order {{ order_number }} is {{ dispatch_status }}.',
                            'ar' => '{{ app_name }}: إرسال {{ courier_name }} للطلب {{ order_number }} هو {{ dispatch_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, external courier {{ courier_name }} updated order {{ order_number }} to {{ dispatch_status }}.',
                            'ar' => 'مرحباً {{ user_name }}، قامت شركة التوصيل {{ courier_name }} بتحديث الطلب {{ order_number }} إلى {{ dispatch_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_tracking_stale' => [
                'key' => 'driver_tracking_stale',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Tracking Delayed',
                    'ar' => 'تأخر تحديث تتبع السائق',
                ],
                'description' => [
                    'en' => 'Sent when driver tracking has not been updated within the expected interval.',
                    'ar' => 'يُرسل عند عدم تحديث تتبع السائق ضمن الفترة المتوقعة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'last_seen_at',
                    'expected_interval',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Driver tracking delayed for {{ driver_name }}',
                            'ar' => 'تأخر تحديث تتبع السائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Driver tracking for {{ driver_name }} in {{ branch_name }} has not been updated since {{ last_seen_at }}.</p><p><strong>Expected update interval:</strong> {{ expected_interval }}</p><p>Please check the driver device, app session, and location service if the delay continues.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>لم يتم تحديث تتبع السائق {{ driver_name }} في {{ branch_name }} منذ {{ last_seen_at }}.</p><p><strong>الفترة المتوقعة للتحديث:</strong> {{ expected_interval }}</p><p>يرجى التحقق من جهاز السائق وجلسة التطبيق وخدمة الموقع إذا استمر التأخير.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, tracking delayed for {{ driver_name }} in {{ branch_name }}. Last seen: {{ last_seen_at }}. Expected interval: {{ expected_interval }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، تأخر تتبع {{ driver_name }} في {{ branch_name }}. آخر ظهور: {{ last_seen_at }}. الفترة المتوقعة: {{ expected_interval }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Tracking delayed for {{ driver_name }}. Last seen: {{ last_seen_at }}.',
                            'ar' => 'تأخر تتبع {{ driver_name }}. آخر ظهور: {{ last_seen_at }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Tracking delayed for {{ driver_name }} in {{ branch_name }}. Last seen: {{ last_seen_at }}.',
                            'ar' => '{{ app_name }}: تأخر تتبع {{ driver_name }} في {{ branch_name }}. آخر ظهور: {{ last_seen_at }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, tracking for {{ driver_name }} in {{ branch_name }} has not updated since {{ last_seen_at }}. Expected interval: {{ expected_interval }}.',
                            'ar' => 'مرحباً {{ user_name }}، لم يتم تحديث تتبع {{ driver_name }} في {{ branch_name }} منذ {{ last_seen_at }}. الفترة المتوقعة: {{ expected_interval }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_tracking_offline' => [
                'key' => 'driver_tracking_offline',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Tracking Offline',
                    'ar' => 'تتبع السائق غير متصل',
                ],
                'description' => [
                    'en' => 'Sent when driver tracking goes offline.',
                    'ar' => 'يُرسل عند انقطاع تتبع السائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'last_seen_at',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Driver tracking offline for {{ driver_name }}',
                            'ar' => 'تتبع السائق {{ driver_name }} غير متصل',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Driver tracking for {{ driver_name }} in {{ branch_name }} is currently offline.</p><p><strong>Last update:</strong> {{ last_seen_at }}</p><p>Please review the driver connection, device battery, and location permissions.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تتبع السائق {{ driver_name }} في {{ branch_name }} غير متصل حالياً.</p><p><strong>آخر تحديث:</strong> {{ last_seen_at }}</p><p>يرجى مراجعة اتصال السائق وحالة البطارية وصلاحيات الموقع.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, tracking offline for {{ driver_name }} in {{ branch_name }}. Last update: {{ last_seen_at }}.',
                            'ar' => 'مرحباً {{ user_name }}، تتبع {{ driver_name }} في {{ branch_name }} غير متصل. آخر تحديث: {{ last_seen_at }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Tracking offline for {{ driver_name }}.',
                            'ar' => 'تتبع {{ driver_name }} غير متصل.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Tracking offline for {{ driver_name }} in {{ branch_name }}. Last update: {{ last_seen_at }}.',
                            'ar' => '{{ app_name }}: تتبع {{ driver_name }} في {{ branch_name }} غير متصل. آخر تحديث: {{ last_seen_at }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, tracking for {{ driver_name }} in {{ branch_name }} is offline. Last update: {{ last_seen_at }}.',
                            'ar' => 'مرحباً {{ user_name }}، تتبع {{ driver_name }} في {{ branch_name }} غير متصل. آخر تحديث: {{ last_seen_at }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_tracking_permission_issue' => [
                'key' => 'driver_tracking_permission_issue',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Tracking Permission Issue',
                    'ar' => 'مشكلة صلاحيات تتبع السائق',
                ],
                'description' => [
                    'en' => 'Sent when driver tracking has a permission issue.',
                    'ar' => 'يُرسل عند وجود مشكلة صلاحية في تتبع السائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'permission_name',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Tracking permission issue for {{ driver_name }}',
                            'ar' => 'مشكلة صلاحيات التتبع للسائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>A tracking permission issue was detected for {{ driver_name }} in {{ branch_name }}.</p><p><strong>Permission:</strong> {{ permission_name }}</p><p>Tracking may be inaccurate until the permission is restored on the driver device.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم اكتشاف مشكلة صلاحيات تتبع لدى السائق {{ driver_name }} في {{ branch_name }}.</p><p><strong>الصلاحية:</strong> {{ permission_name }}</p><p>قد لا يكون التتبع دقيقاً حتى تتم استعادة الصلاحية على جهاز السائق.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, {{ permission_name }} permission issue for {{ driver_name }} in {{ branch_name }}.',
                            'ar' => 'مرحباً {{ user_name }}، توجد مشكلة صلاحية {{ permission_name }} لدى {{ driver_name }} في {{ branch_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Tracking permission issue for {{ driver_name }}.',
                            'ar' => 'مشكلة صلاحية تتبع لدى {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ permission_name }} permission issue for {{ driver_name }} in {{ branch_name }}.',
                            'ar' => '{{ app_name }}: مشكلة صلاحية {{ permission_name }} لدى {{ driver_name }} في {{ branch_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ permission_name }} permission issue was detected for {{ driver_name }} in {{ branch_name }}. Tracking may be inaccurate until fixed.',
                            'ar' => 'مرحباً {{ user_name }}، تم اكتشاف مشكلة في صلاحية {{ permission_name }} لدى {{ driver_name }} في {{ branch_name }}. قد لا يكون التتبع دقيقاً حتى يتم إصلاحها.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_tracking_token_issue' => [
                'key' => 'driver_tracking_token_issue',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Tracking Token Issue',
                    'ar' => 'مشكلة رمز تتبع السائق',
                ],
                'description' => [
                    'en' => 'Sent when driver tracking has a token issue.',
                    'ar' => 'يُرسل عند وجود مشكلة رمز في تتبع السائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'token_status',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Tracking token issue for {{ driver_name }}',
                            'ar' => 'مشكلة رمز التتبع للسائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>A tracking token issue was detected for {{ driver_name }} in {{ branch_name }}.</p><p><strong>Token status:</strong> {{ token_status }}</p><p>The driver may need to refresh the app session before tracking becomes reliable again.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم اكتشاف مشكلة رمز تتبع لدى السائق {{ driver_name }} في {{ branch_name }}.</p><p><strong>حالة الرمز:</strong> {{ token_status }}</p><p>قد يحتاج السائق إلى تحديث جلسة التطبيق حتى يعود التتبع للعمل بشكل موثوق.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, tracking token issue for {{ driver_name }} in {{ branch_name }}. Status: {{ token_status }}.',
                            'ar' => 'مرحباً {{ user_name }}، توجد مشكلة رمز تتبع لدى {{ driver_name }} في {{ branch_name }}. الحالة: {{ token_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Tracking token issue for {{ driver_name }}.',
                            'ar' => 'مشكلة رمز تتبع لدى {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Tracking token issue for {{ driver_name }} in {{ branch_name }}. Status: {{ token_status }}.',
                            'ar' => '{{ app_name }}: مشكلة رمز تتبع لدى {{ driver_name }} في {{ branch_name }}. الحالة: {{ token_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, tracking token issue was detected for {{ driver_name }} in {{ branch_name }}. Status: {{ token_status }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم اكتشاف مشكلة رمز تتبع لدى {{ driver_name }} في {{ branch_name }}. الحالة: {{ token_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_cod_declared' => [
                'key' => 'driver_cod_declared',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Cash Declaration Submitted',
                    'ar' => 'تم تقديم تصريح نقدية السائق',
                ],
                'description' => [
                    'en' => 'Sent when a driver submits a cash-on-delivery declaration.',
                    'ar' => 'يُرسل عند تقديم السائق تصريح مبالغ الدفع عند الاستلام.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'declaration_number',
                    'amount',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Cash declaration submitted by {{ driver_name }}',
                            'ar' => 'تم تقديم تصريح نقدية من {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Driver {{ driver_name }} submitted cash declaration {{ declaration_number }} for {{ amount }} {{ currency }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>قدم السائق {{ driver_name }} تصريح النقدية {{ declaration_number }} بمبلغ {{ amount }} {{ currency }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, COD declaration {{ declaration_number }} recorded for {{ driver_name }}: {{ amount }} {{ currency }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، تم تسجيل تصريح الدفع عند الاستلام {{ declaration_number }} للسائق {{ driver_name }}: {{ amount }} {{ currency }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'COD {{ declaration_number }}: {{ amount }} {{ currency }} by {{ driver_name }}.',
                            'ar' => 'COD {{ declaration_number }}: {{ amount }} {{ currency }} بواسطة {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: COD declaration {{ declaration_number }} for {{ driver_name }} is {{ amount }} {{ currency }}.',
                            'ar' => '{{ app_name }}: تصريح COD {{ declaration_number }} للسائق {{ driver_name }} بقيمة {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_cash_settlement_updated' => [
                'key' => 'driver_cash_settlement_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Cash Settlement Updated',
                    'ar' => 'تحديث تسوية نقدية للسائق',
                ],
                'description' => [
                    'en' => 'Sent when a driver cash settlement is updated.',
                    'ar' => 'يُرسل عند تحديث التسوية النقدية للسائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'settlement_status',
                    'amount',
                    'currency',
                    'settlement_number',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Cash settlement updated for {{ driver_name }}',
                            'ar' => 'تم تحديث تسوية النقدية للسائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Cash settlement {{ settlement_number }} for {{ driver_name }} has been updated to {{ settlement_status }}.</p><p><strong>Amount:</strong> {{ amount }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث تسوية النقدية {{ settlement_number }} للسائق {{ driver_name }} إلى {{ settlement_status }}.</p><p><strong>المبلغ:</strong> {{ amount }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Cash settlement {{ settlement_number }} for {{ driver_name }} is {{ settlement_status }}: {{ amount }} {{ currency }}.',
                            'ar' => 'تسوية النقدية {{ settlement_number }} للسائق {{ driver_name }} هي {{ settlement_status }}: {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Settlement {{ settlement_number }} is {{ settlement_status }}.',
                            'ar' => 'التسوية {{ settlement_number }} هي {{ settlement_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Settlement {{ settlement_number }} for {{ driver_name }} is {{ settlement_status }}: {{ amount }} {{ currency }}.',
                            'ar' => '{{ app_name }}: التسوية {{ settlement_number }} للسائق {{ driver_name }} هي {{ settlement_status }}: {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, cash settlement {{ settlement_number }} for {{ driver_name }} is {{ settlement_status }}. Amount: {{ amount }} {{ currency }}.',
                            'ar' => 'مرحباً {{ user_name }}، تسوية النقدية {{ settlement_number }} للسائق {{ driver_name }} هي {{ settlement_status }}. المبلغ: {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_earnings_updated' => [
                'key' => 'driver_earnings_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Earnings Updated',
                    'ar' => 'تحديث أرباح السائق',
                ],
                'description' => [
                    'en' => 'Sent when driver earnings are updated.',
                    'ar' => 'يُرسل عند تحديث أرباح السائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'period',
                    'amount',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Earnings updated for {{ driver_name }}',
                            'ar' => 'تم تحديث أرباح السائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Earnings for {{ driver_name }} have been updated for {{ period }}. Amount: {{ amount }} {{ currency }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث أرباح السائق {{ driver_name }} للفترة {{ period }}. المبلغ: {{ amount }} {{ currency }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Earnings updated for {{ driver_name }}.',
                            'ar' => 'تم تحديث أرباح السائق {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Earnings updated for {{ driver_name }}.',
                            'ar' => 'تم تحديث أرباح السائق {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Earnings updated for {{ driver_name }}. - {{ app_name }}',
                            'ar' => 'تم تحديث أرباح السائق {{ driver_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, earnings for {{ driver_name }} were updated for {{ period }}. Amount: {{ amount }} {{ currency }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم تحديث أرباح {{ driver_name }} للفترة {{ period }}. المبلغ: {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_payout_updated' => [
                'key' => 'driver_payout_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Payout Updated',
                    'ar' => 'تحديث دفعة السائق',
                ],
                'description' => [
                    'en' => 'Sent when a driver payout is updated.',
                    'ar' => 'يُرسل عند تحديث دفعة السائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'payout_status',
                    'amount',
                    'currency',
                    'payout_number',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payout updated for {{ driver_name }}',
                            'ar' => 'تم تحديث دفعة السائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Payout {{ payout_number }} for {{ driver_name }} has been updated to {{ payout_status }}.</p><p><strong>Amount:</strong> {{ amount }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث الدفعة {{ payout_number }} للسائق {{ driver_name }} إلى {{ payout_status }}.</p><p><strong>المبلغ:</strong> {{ amount }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payout {{ payout_number }} for {{ driver_name }} is {{ payout_status }}: {{ amount }} {{ currency }}.',
                            'ar' => 'الدفعة {{ payout_number }} للسائق {{ driver_name }} هي {{ payout_status }}: {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payout {{ payout_number }} is {{ payout_status }}.',
                            'ar' => 'الدفعة {{ payout_number }} هي {{ payout_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Payout {{ payout_number }} for {{ driver_name }} is {{ payout_status }}: {{ amount }} {{ currency }}.',
                            'ar' => '{{ app_name }}: الدفعة {{ payout_number }} للسائق {{ driver_name }} هي {{ payout_status }}: {{ amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_payout_issue_reported' => [
                'key' => 'driver_payout_issue_reported',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Payout Issue Reported',
                    'ar' => 'الإبلاغ عن مشكلة دفعة للسائق',
                ],
                'description' => [
                    'en' => 'Sent when a driver payout issue is reported.',
                    'ar' => 'يُرسل عند الإبلاغ عن مشكلة دفعة للسائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'payout_number',
                    'issue_summary',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payout issue reported for {{ driver_name }}',
                            'ar' => 'تم الإبلاغ عن مشكلة دفعة للسائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>A payout issue has been reported for {{ driver_name }}. Payout reference: {{ payout_number }}. Issue: {{ issue_summary }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم الإبلاغ عن مشكلة دفعة للسائق {{ driver_name }}. مرجع الدفعة: {{ payout_number }}. ملخص المشكلة: {{ issue_summary }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, payout issue reported for {{ driver_name }}. Reference: {{ payout_number }}. Issue: {{ issue_summary }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، تم الإبلاغ عن مشكلة دفعة للسائق {{ driver_name }}. المرجع: {{ payout_number }}. المشكلة: {{ issue_summary }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payout issue for {{ driver_name }}. Ref: {{ payout_number }}.',
                            'ar' => 'مشكلة دفعة للسائق {{ driver_name }}. المرجع: {{ payout_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Payout issue for {{ driver_name }}. Ref: {{ payout_number }}. Issue: {{ issue_summary }}.',
                            'ar' => '{{ app_name }}: مشكلة دفعة للسائق {{ driver_name }}. المرجع: {{ payout_number }}. المشكلة: {{ issue_summary }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'driver_payout_issue_resolved' => [
                'key' => 'driver_payout_issue_resolved',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Driver Payout Issue Resolved',
                    'ar' => 'حل مشكلة دفعة للسائق',
                ],
                'description' => [
                    'en' => 'Sent when a driver payout issue is resolved.',
                    'ar' => 'يُرسل عند حل مشكلة دفعة للسائق.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'driver_name',
                    'payout_number',
                    'resolution_summary',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payout issue resolved for {{ driver_name }}',
                            'ar' => 'تم حل مشكلة دفعة للسائق {{ driver_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The payout issue for {{ driver_name }} has been resolved.</p><p><strong>Payout reference:</strong> {{ payout_number }}<br><strong>Resolution:</strong> {{ resolution_summary }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم حل مشكلة الدفعة الخاصة بالسائق {{ driver_name }}.</p><p><strong>مرجع الدفعة:</strong> {{ payout_number }}<br><strong>الحل:</strong> {{ resolution_summary }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payout issue resolved for {{ driver_name }}. Ref: {{ payout_number }}. Resolution: {{ resolution_summary }}.',
                            'ar' => 'تم حل مشكلة الدفعة للسائق {{ driver_name }}. المرجع: {{ payout_number }}. الحل: {{ resolution_summary }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payout issue resolved for {{ driver_name }}.',
                            'ar' => 'تم حل مشكلة الدفعة للسائق {{ driver_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Payout issue resolved for {{ driver_name }}. Ref: {{ payout_number }}.',
                            'ar' => '{{ app_name }}: تم حل مشكلة الدفعة للسائق {{ driver_name }}. المرجع: {{ payout_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'employee_created' => [
                'key' => 'employee_created',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Employee Created',
                    'ar' => 'إنشاء الموظف',
                ],
                'description' => [
                    'en' => 'Sent when an employee is created.',
                    'ar' => 'يُرسل عند إنشاء الموظف.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'employee_code',
                    'start_date',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Employee profile created for {{ employee_name }}',
                            'ar' => 'تم إنشاء ملف الموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Employee {{ employee_name }} has been created successfully in {{ branch_name }}.</p><p><strong>Employee code:</strong> {{ employee_code }}<br><strong>Start date:</strong> {{ start_date }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إنشاء الموظف {{ employee_name }} بنجاح في {{ branch_name }}.</p><p><strong>رمز الموظف:</strong> {{ employee_code }}<br><strong>تاريخ البدء:</strong> {{ start_date }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Employee {{ employee_name }} ({{ employee_code }}) was created in {{ branch_name }}.',
                            'ar' => 'تم إنشاء الموظف {{ employee_name }} ({{ employee_code }}) في {{ branch_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Employee {{ employee_name }} created in {{ branch_name }}.',
                            'ar' => 'تم إنشاء الموظف {{ employee_name }} في {{ branch_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Employee {{ employee_name }} ({{ employee_code }}) created in {{ branch_name }}. Start date: {{ start_date }}.',
                            'ar' => '{{ app_name }}: تم إنشاء الموظف {{ employee_name }} ({{ employee_code }}) في {{ branch_name }}. تاريخ البدء: {{ start_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'employment_profile_updated' => [
                'key' => 'employment_profile_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Employment Profile Updated',
                    'ar' => 'تحديث الملف الوظيفي',
                ],
                'description' => [
                    'en' => 'Sent when an employment profile is updated.',
                    'ar' => 'يُرسل عند تحديث الملف الوظيفي.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'employee_code',
                    'updated_at',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Employment profile updated for {{ employee_name }}',
                            'ar' => 'تم تحديث الملف الوظيفي للموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The employment profile for {{ employee_name }} ({{ employee_code }}) was updated on {{ updated_at }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث الملف الوظيفي للموظف {{ employee_name }} ({{ employee_code }}) بتاريخ {{ updated_at }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Employment profile updated for {{ employee_name }} ({{ employee_code }}).',
                            'ar' => 'تم تحديث الملف الوظيفي للموظف {{ employee_name }} ({{ employee_code }}).',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Profile updated for {{ employee_name }}.',
                            'ar' => 'تم تحديث ملف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Employment profile updated for {{ employee_name }} ({{ employee_code }}) on {{ updated_at }}.',
                            'ar' => '{{ app_name }}: تم تحديث الملف الوظيفي للموظف {{ employee_name }} ({{ employee_code }}) بتاريخ {{ updated_at }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'attendance_event_recorded' => [
                'key' => 'attendance_event_recorded',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Attendance Event Recorded',
                    'ar' => 'تسجيل حدث حضور',
                ],
                'description' => [
                    'en' => 'Sent when an attendance event is recorded.',
                    'ar' => 'يُرسل عند تسجيل حدث حضور.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'attendance_type',
                    'attendance_time',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Attendance recorded for {{ employee_name }}',
                            'ar' => 'تم تسجيل حضور الموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Attendance event {{ attendance_type }} was recorded for {{ employee_name }} at {{ attendance_time }} in {{ branch_name }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تسجيل حدث الحضور {{ attendance_type }} للموظف {{ employee_name }} في {{ attendance_time }} ضمن {{ branch_name }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, attendance {{ attendance_type }} recorded for {{ employee_name }} at {{ attendance_time }} in {{ branch_name }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، تم تسجيل الحضور {{ attendance_type }} للموظف {{ employee_name }} في {{ attendance_time }} ضمن {{ branch_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ employee_name }}: {{ attendance_type }} at {{ attendance_time }}.',
                            'ar' => '{{ employee_name }}: {{ attendance_type }} في {{ attendance_time }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Attendance {{ attendance_type }} for {{ employee_name }} at {{ attendance_time }} in {{ branch_name }}.',
                            'ar' => '{{ app_name }}: الحضور {{ attendance_type }} للموظف {{ employee_name }} في {{ attendance_time }} ضمن {{ branch_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'attendance_manual_adjusted' => [
                'key' => 'attendance_manual_adjusted',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Attendance Manually Adjusted',
                    'ar' => 'تعديل الحضور يدوياً',
                ],
                'description' => [
                    'en' => 'Sent when attendance is manually adjusted.',
                    'ar' => 'يُرسل عند تعديل الحضور يدوياً.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'attendance_date',
                    'adjusted_by',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Attendance manually adjusted for {{ employee_name }}',
                            'ar' => 'تم تعديل حضور الموظف {{ employee_name }} يدوياً',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The attendance record for {{ employee_name }} on {{ attendance_date }} was manually adjusted by {{ adjusted_by }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تعديل سجل حضور الموظف {{ employee_name }} بتاريخ {{ attendance_date }} يدوياً بواسطة {{ adjusted_by }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Attendance manually adjusted for {{ employee_name }}.',
                            'ar' => 'تم تعديل حضور الموظف {{ employee_name }} يدوياً.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Attendance manually adjusted for {{ employee_name }}.',
                            'ar' => 'تم تعديل حضور الموظف {{ employee_name }} يدوياً.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Attendance manually adjusted for {{ employee_name }}. - {{ app_name }}',
                            'ar' => 'تم تعديل حضور الموظف {{ employee_name }} يدوياً. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'attendance_record_lock_changed' => [
                'key' => 'attendance_record_lock_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Attendance Record Lock Changed',
                    'ar' => 'تغيير قفل سجل الحضور',
                ],
                'description' => [
                    'en' => 'Sent when an attendance record lock changes.',
                    'ar' => 'يُرسل عند تغيير قفل سجل الحضور.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'attendance_date',
                    'lock_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Attendance lock changed for {{ employee_name }}',
                            'ar' => 'تم تغيير قفل الحضور للموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The attendance record lock for {{ employee_name }} on {{ attendance_date }} has changed to {{ lock_status }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تغيير حالة قفل سجل حضور الموظف {{ employee_name }} بتاريخ {{ attendance_date }} إلى {{ lock_status }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Attendance lock changed to {{ lock_status }} for {{ employee_name }}.',
                            'ar' => 'تم تغيير قفل الحضور إلى {{ lock_status }} للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Attendance lock changed to {{ lock_status }} for {{ employee_name }}.',
                            'ar' => 'تم تغيير قفل الحضور إلى {{ lock_status }} للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Attendance lock changed to {{ lock_status }} for {{ employee_name }}. - {{ app_name }}',
                            'ar' => 'تم تغيير قفل الحضور إلى {{ lock_status }} للموظف {{ employee_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'leave_request_submitted' => [
                'key' => 'leave_request_submitted',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Leave Request Submitted',
                    'ar' => 'تقديم طلب إجازة',
                ],
                'description' => [
                    'en' => 'Sent when a leave request is submitted.',
                    'ar' => 'يُرسل عند تقديم طلب إجازة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'leave_type',
                    'start_date',
                    'end_date',
                    'leave_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Leave request submitted by {{ employee_name }}',
                            'ar' => 'تم تقديم طلب إجازة من {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>{{ employee_name }} submitted a {{ leave_type }} leave request from {{ start_date }} to {{ end_date }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>قدم الموظف {{ employee_name }} طلب إجازة {{ leave_type }} من {{ start_date }} إلى {{ end_date }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Leave request for {{ employee_name }} is {{ leave_status }}: {{ leave_type }} from {{ start_date }} to {{ end_date }}.',
                            'ar' => 'طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}: {{ leave_type }} من {{ start_date }} إلى {{ end_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Leave request submitted for {{ employee_name }}: {{ leave_status }}.',
                            'ar' => 'تم تقديم طلب إجازة للموظف {{ employee_name }}: {{ leave_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Leave request for {{ employee_name }} is {{ leave_status }}. {{ leave_type }} from {{ start_date }} to {{ end_date }}.',
                            'ar' => '{{ app_name }}: طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}. {{ leave_type }} من {{ start_date }} إلى {{ end_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'leave_request_approved' => [
                'key' => 'leave_request_approved',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Leave Request Approved',
                    'ar' => 'الموافقة على طلب الإجازة',
                ],
                'description' => [
                    'en' => 'Sent when a leave request is approved.',
                    'ar' => 'يُرسل عند الموافقة على طلب الإجازة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'leave_type',
                    'start_date',
                    'end_date',
                    'leave_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Leave request approved for {{ employee_name }}',
                            'ar' => 'تمت الموافقة على طلب إجازة {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The {{ leave_type }} leave request for {{ employee_name }} from {{ start_date }} to {{ end_date }} has been approved.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تمت الموافقة على طلب إجازة {{ leave_type }} للموظف {{ employee_name }} من {{ start_date }} إلى {{ end_date }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Leave request for {{ employee_name }} is {{ leave_status }}: {{ leave_type }} from {{ start_date }} to {{ end_date }}.',
                            'ar' => 'طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}: {{ leave_type }} من {{ start_date }} إلى {{ end_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Leave approved for {{ employee_name }}: {{ leave_status }}.',
                            'ar' => 'تمت الموافقة على إجازة {{ employee_name }}: {{ leave_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Leave request for {{ employee_name }} is {{ leave_status }}. {{ leave_type }} from {{ start_date }} to {{ end_date }}.',
                            'ar' => '{{ app_name }}: طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}. {{ leave_type }} من {{ start_date }} إلى {{ end_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'leave_request_rejected' => [
                'key' => 'leave_request_rejected',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Leave Request Rejected',
                    'ar' => 'رفض طلب الإجازة',
                ],
                'description' => [
                    'en' => 'Sent when a leave request is rejected.',
                    'ar' => 'يُرسل عند رفض طلب الإجازة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'leave_type',
                    'start_date',
                    'end_date',
                    'rejection_reason',
                    'leave_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Leave request rejected for {{ employee_name }}',
                            'ar' => 'تم رفض طلب إجازة {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The {{ leave_type }} leave request for {{ employee_name }} from {{ start_date }} to {{ end_date }} has been rejected. Reason: {{ rejection_reason }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم رفض طلب إجازة {{ leave_type }} للموظف {{ employee_name }} من {{ start_date }} إلى {{ end_date }}. السبب: {{ rejection_reason }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Leave request for {{ employee_name }} is {{ leave_status }}. Reason: {{ rejection_reason }}.',
                            'ar' => 'طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}. السبب: {{ rejection_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Leave rejected for {{ employee_name }}: {{ leave_status }}.',
                            'ar' => 'تم رفض إجازة {{ employee_name }}: {{ leave_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Leave request for {{ employee_name }} is {{ leave_status }}. Reason: {{ rejection_reason }}.',
                            'ar' => '{{ app_name }}: طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}. السبب: {{ rejection_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'leave_request_cancelled' => [
                'key' => 'leave_request_cancelled',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Leave Request Cancelled',
                    'ar' => 'إلغاء طلب الإجازة',
                ],
                'description' => [
                    'en' => 'Sent when a leave request is cancelled.',
                    'ar' => 'يُرسل عند إلغاء طلب الإجازة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'leave_type',
                    'start_date',
                    'end_date',
                    'cancellation_reason',
                    'leave_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Leave request cancelled for {{ employee_name }}',
                            'ar' => 'تم إلغاء طلب إجازة {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The {{ leave_type }} leave request for {{ employee_name }} from {{ start_date }} to {{ end_date }} has been cancelled.</p><p><strong>Status:</strong> {{ leave_status }}<br><strong>Reason:</strong> {{ cancellation_reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إلغاء طلب إجازة {{ leave_type }} للموظف {{ employee_name }} من {{ start_date }} إلى {{ end_date }}.</p><p><strong>الحالة:</strong> {{ leave_status }}<br><strong>السبب:</strong> {{ cancellation_reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Leave request for {{ employee_name }} is {{ leave_status }}. Reason: {{ cancellation_reason }}.',
                            'ar' => 'طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Leave cancelled for {{ employee_name }}: {{ leave_status }}.',
                            'ar' => 'تم إلغاء إجازة {{ employee_name }}: {{ leave_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Leave request for {{ employee_name }} is {{ leave_status }}. Reason: {{ cancellation_reason }}.',
                            'ar' => '{{ app_name }}: طلب الإجازة للموظف {{ employee_name }} حالته {{ leave_status }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'overtime_request_submitted' => [
                'key' => 'overtime_request_submitted',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Overtime Request Submitted',
                    'ar' => 'تقديم طلب عمل إضافي',
                ],
                'description' => [
                    'en' => 'Sent when an overtime request is submitted.',
                    'ar' => 'يُرسل عند تقديم طلب عمل إضافي.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'overtime_date',
                    'overtime_hours',
                    'overtime_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Overtime request submitted by {{ employee_name }}',
                            'ar' => 'تم تقديم طلب عمل إضافي من {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>{{ employee_name }} submitted an overtime request for {{ overtime_date }}. Requested hours: {{ overtime_hours }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>قدم الموظف {{ employee_name }} طلب عمل إضافي بتاريخ {{ overtime_date }}. عدد الساعات المطلوبة: {{ overtime_hours }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, overtime request for {{ employee_name }} is {{ overtime_status }}: {{ overtime_hours }} hours on {{ overtime_date }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، طلب العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}: {{ overtime_hours }} ساعات بتاريخ {{ overtime_date }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Overtime submitted for {{ employee_name }}: {{ overtime_status }}.',
                            'ar' => 'تم تقديم عمل إضافي للموظف {{ employee_name }}: {{ overtime_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Overtime for {{ employee_name }} is {{ overtime_status }}: {{ overtime_hours }} hours on {{ overtime_date }}.',
                            'ar' => '{{ app_name }}: العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}: {{ overtime_hours }} ساعات بتاريخ {{ overtime_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'overtime_request_approved' => [
                'key' => 'overtime_request_approved',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Overtime Request Approved',
                    'ar' => 'الموافقة على طلب العمل الإضافي',
                ],
                'description' => [
                    'en' => 'Sent when an overtime request is approved.',
                    'ar' => 'يُرسل عند الموافقة على طلب العمل الإضافي.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'overtime_date',
                    'overtime_hours',
                    'overtime_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Overtime request approved for {{ employee_name }}',
                            'ar' => 'تمت الموافقة على طلب العمل الإضافي للموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The overtime request for {{ employee_name }} on {{ overtime_date }} has been approved. Approved hours: {{ overtime_hours }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تمت الموافقة على طلب العمل الإضافي للموظف {{ employee_name }} بتاريخ {{ overtime_date }}. عدد الساعات المعتمدة: {{ overtime_hours }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Overtime request for {{ employee_name }} is {{ overtime_status }}: {{ overtime_hours }} hours on {{ overtime_date }}.',
                            'ar' => 'طلب العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}: {{ overtime_hours }} ساعات بتاريخ {{ overtime_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Overtime approved for {{ employee_name }}: {{ overtime_status }}.',
                            'ar' => 'تمت الموافقة على العمل الإضافي للموظف {{ employee_name }}: {{ overtime_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Overtime for {{ employee_name }} is {{ overtime_status }}: {{ overtime_hours }} hours on {{ overtime_date }}.',
                            'ar' => '{{ app_name }}: العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}: {{ overtime_hours }} ساعات بتاريخ {{ overtime_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'overtime_request_rejected' => [
                'key' => 'overtime_request_rejected',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Overtime Request Rejected',
                    'ar' => 'رفض طلب العمل الإضافي',
                ],
                'description' => [
                    'en' => 'Sent when an overtime request is rejected.',
                    'ar' => 'يُرسل عند رفض طلب العمل الإضافي.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'overtime_date',
                    'overtime_hours',
                    'rejection_reason',
                    'overtime_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Overtime request rejected for {{ employee_name }}',
                            'ar' => 'تم رفض طلب العمل الإضافي للموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The overtime request for {{ employee_name }} on {{ overtime_date }} has been rejected. Reason: {{ rejection_reason }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم رفض طلب العمل الإضافي للموظف {{ employee_name }} بتاريخ {{ overtime_date }}. السبب: {{ rejection_reason }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Overtime request for {{ employee_name }} is {{ overtime_status }}: {{ overtime_hours }} hours on {{ overtime_date }}. Reason: {{ rejection_reason }}.',
                            'ar' => 'طلب العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}: {{ overtime_hours }} ساعات بتاريخ {{ overtime_date }}. السبب: {{ rejection_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Overtime rejected for {{ employee_name }}: {{ overtime_status }}.',
                            'ar' => 'تم رفض العمل الإضافي للموظف {{ employee_name }}: {{ overtime_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Overtime for {{ employee_name }} is {{ overtime_status }}. Hours: {{ overtime_hours }}. Reason: {{ rejection_reason }}.',
                            'ar' => '{{ app_name }}: العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}. الساعات: {{ overtime_hours }}. السبب: {{ rejection_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'overtime_request_cancelled' => [
                'key' => 'overtime_request_cancelled',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Overtime Request Cancelled',
                    'ar' => 'إلغاء طلب العمل الإضافي',
                ],
                'description' => [
                    'en' => 'Sent when an overtime request is cancelled.',
                    'ar' => 'يُرسل عند إلغاء طلب العمل الإضافي.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'overtime_date',
                    'overtime_hours',
                    'cancellation_reason',
                    'overtime_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Overtime request cancelled for {{ employee_name }}',
                            'ar' => 'تم إلغاء طلب العمل الإضافي للموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The overtime request for {{ employee_name }} on {{ overtime_date }} has been cancelled.</p><p><strong>Hours:</strong> {{ overtime_hours }}<br><strong>Status:</strong> {{ overtime_status }}<br><strong>Reason:</strong> {{ cancellation_reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إلغاء طلب العمل الإضافي للموظف {{ employee_name }} بتاريخ {{ overtime_date }}.</p><p><strong>الساعات:</strong> {{ overtime_hours }}<br><strong>الحالة:</strong> {{ overtime_status }}<br><strong>السبب:</strong> {{ cancellation_reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Overtime request for {{ employee_name }} is {{ overtime_status }}: {{ overtime_hours }} hours on {{ overtime_date }}. Reason: {{ cancellation_reason }}.',
                            'ar' => 'طلب العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}: {{ overtime_hours }} ساعات بتاريخ {{ overtime_date }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Overtime cancelled for {{ employee_name }}: {{ overtime_status }}.',
                            'ar' => 'تم إلغاء العمل الإضافي للموظف {{ employee_name }}: {{ overtime_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Overtime for {{ employee_name }} is {{ overtime_status }}. Hours: {{ overtime_hours }}. Reason: {{ cancellation_reason }}.',
                            'ar' => '{{ app_name }}: العمل الإضافي للموظف {{ employee_name }} حالته {{ overtime_status }}. الساعات: {{ overtime_hours }}. السبب: {{ cancellation_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'payroll_run_updated' => [
                'key' => 'payroll_run_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Payroll Run Updated',
                    'ar' => 'تحديث تشغيل الرواتب',
                ],
                'description' => [
                    'en' => 'Sent when a payroll run is updated.',
                    'ar' => 'يُرسل عند تحديث تشغيل الرواتب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'payroll_period',
                    'payroll_status',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payroll run updated for {{ payroll_period }}',
                            'ar' => 'تم تحديث تشغيل الرواتب للفترة {{ payroll_period }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The payroll run for {{ payroll_period }} has been updated to {{ payroll_status }} for {{ branch_name }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث تشغيل الرواتب للفترة {{ payroll_period }} إلى {{ payroll_status }} في {{ branch_name }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payroll run {{ payroll_period }} is now {{ payroll_status }}.',
                            'ar' => 'أصبح تشغيل الرواتب للفترة {{ payroll_period }} الآن {{ payroll_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payroll run {{ payroll_period }} is now {{ payroll_status }}.',
                            'ar' => 'أصبح تشغيل الرواتب للفترة {{ payroll_period }} الآن {{ payroll_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Payroll run {{ payroll_period }} is now {{ payroll_status }}. - {{ app_name }}',
                            'ar' => 'أصبح تشغيل الرواتب للفترة {{ payroll_period }} الآن {{ payroll_status }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'payslip_updated' => [
                'key' => 'payslip_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Payslip Updated',
                    'ar' => 'تحديث كشف الراتب',
                ],
                'description' => [
                    'en' => 'Sent when a payslip is updated.',
                    'ar' => 'يُرسل عند تحديث كشف الراتب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'payroll_period',
                    'net_salary',
                    'currency',
                    'payslip_status',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payslip updated for {{ payroll_period }}',
                            'ar' => 'تم تحديث كشف الراتب للفترة {{ payroll_period }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The payslip for {{ employee_name }} for {{ payroll_period }} has been updated. Net salary: {{ net_salary }} {{ currency }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث كشف راتب الموظف {{ employee_name }} للفترة {{ payroll_period }}. صافي الراتب: {{ net_salary }} {{ currency }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payslip for {{ employee_name }} - {{ payroll_period }} is {{ payslip_status }}. Net salary: {{ net_salary }} {{ currency }}.',
                            'ar' => 'قسيمة راتب {{ employee_name }} - {{ payroll_period }} حالتها {{ payslip_status }}. صافي الراتب: {{ net_salary }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payslip {{ payroll_period }} is {{ payslip_status }}.',
                            'ar' => 'قسيمة راتب {{ payroll_period }} حالتها {{ payslip_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Payslip for {{ employee_name }} - {{ payroll_period }} is {{ payslip_status }}. Net: {{ net_salary }} {{ currency }}.',
                            'ar' => '{{ app_name }}: قسيمة راتب {{ employee_name }} - {{ payroll_period }} حالتها {{ payslip_status }}. الصافي: {{ net_salary }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'payroll_payment_updated' => [
                'key' => 'payroll_payment_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Payroll Payment Updated',
                    'ar' => 'تحديث دفعة الرواتب',
                ],
                'description' => [
                    'en' => 'Sent when a payroll payment is updated.',
                    'ar' => 'يُرسل عند تحديث دفعة الرواتب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'payroll_period',
                    'payment_status',
                    'amount',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payroll payment updated for {{ employee_name }}',
                            'ar' => 'تم تحديث دفعة راتب الموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The payroll payment for {{ employee_name }} for {{ payroll_period }} is now {{ payment_status }}. Amount: {{ amount }} {{ currency }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>أصبحت دفعة راتب الموظف {{ employee_name }} للفترة {{ payroll_period }} الآن {{ payment_status }}. المبلغ: {{ amount }} {{ currency }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payroll payment for {{ employee_name }} is now {{ payment_status }}.',
                            'ar' => 'أصبحت دفعة راتب الموظف {{ employee_name }} الآن {{ payment_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payroll payment for {{ employee_name }} is now {{ payment_status }}.',
                            'ar' => 'أصبحت دفعة راتب الموظف {{ employee_name }} الآن {{ payment_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Payroll payment for {{ employee_name }} is now {{ payment_status }}. - {{ app_name }}',
                            'ar' => 'أصبحت دفعة راتب الموظف {{ employee_name }} الآن {{ payment_status }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'employee_document_available' => [
                'key' => 'employee_document_available',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Employee Document Available',
                    'ar' => 'توفر مستند الموظف',
                ],
                'description' => [
                    'en' => 'Sent when an employee document becomes available.',
                    'ar' => 'يُرسل عند توفر مستند الموظف.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'document_name',
                    'document_type',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Document available for {{ employee_name }}',
                            'ar' => 'مستند متاح للموظف {{ employee_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The {{ document_type }} document {{ document_name }} is now available for {{ employee_name }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>أصبح مستند {{ document_type }} باسم {{ document_name }} متاحاً للموظف {{ employee_name }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => '{{ document_type }} document {{ document_name }} is available for {{ employee_name }}.',
                            'ar' => 'مستند {{ document_type }} باسم {{ document_name }} متاح للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => '{{ document_name }} is available for {{ employee_name }}.',
                            'ar' => '{{ document_name }} متاح للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: {{ document_type }} document {{ document_name }} is available for {{ employee_name }}.',
                            'ar' => '{{ app_name }}: مستند {{ document_type }} باسم {{ document_name }} متاح للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'payslip_file_available' => [
                'key' => 'payslip_file_available',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Payslip File Available',
                    'ar' => 'توفر ملف كشف الراتب',
                ],
                'description' => [
                    'en' => 'Sent when a payslip file becomes available.',
                    'ar' => 'يُرسل عند توفر ملف كشف الراتب.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'employee_name',
                    'payroll_period',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Payslip file available for {{ payroll_period }}',
                            'ar' => 'ملف كشف الراتب متاح للفترة {{ payroll_period }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The payslip file for {{ employee_name }} for {{ payroll_period }} is now available.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>أصبح ملف كشف راتب الموظف {{ employee_name }} للفترة {{ payroll_period }} متاحاً الآن.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Payslip file available for {{ employee_name }}.',
                            'ar' => 'ملف كشف الراتب متاح للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Payslip file available for {{ employee_name }}.',
                            'ar' => 'ملف كشف الراتب متاح للموظف {{ employee_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Payslip file available for {{ employee_name }}. - {{ app_name }}',
                            'ar' => 'ملف كشف الراتب متاح للموظف {{ employee_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'pos_session_opened' => [
                'key' => 'pos_session_opened',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'POS Session Opened',
                    'ar' => 'فتح جلسة نقطة البيع',
                ],
                'description' => [
                    'en' => 'Sent when a POS session is opened.',
                    'ar' => 'يُرسل عند فتح جلسة نقطة البيع.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'session_number',
                    'cashier_name',
                    'opened_at',
                    'branch_name',
                    'opening_amount',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'POS session {{ session_number }} opened',
                            'ar' => 'تم فتح جلسة نقطة البيع {{ session_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>POS session {{ session_number }} was opened by {{ cashier_name }} at {{ opened_at }} for {{ branch_name }}.</p><p><strong>Opening amount:</strong> {{ opening_amount }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم فتح جلسة نقطة البيع {{ session_number }} بواسطة {{ cashier_name }} في {{ opened_at }} ضمن {{ branch_name }}.</p><p><strong>مبلغ الافتتاح:</strong> {{ opening_amount }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, POS session {{ session_number }} opened by {{ cashier_name }} at {{ opened_at }} for {{ branch_name }}. Opening: {{ opening_amount }} {{ currency }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، تم فتح جلسة نقطة البيع {{ session_number }} بواسطة {{ cashier_name }} في {{ opened_at }} ضمن {{ branch_name }}. الافتتاح: {{ opening_amount }} {{ currency }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'POS session {{ session_number }} opened.',
                            'ar' => 'تم فتح جلسة نقطة البيع {{ session_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: POS session {{ session_number }} opened by {{ cashier_name }} at {{ branch_name }}. Opening: {{ opening_amount }} {{ currency }}.',
                            'ar' => '{{ app_name }}: تم فتح جلسة نقطة البيع {{ session_number }} بواسطة {{ cashier_name }} في {{ branch_name }}. الافتتاح: {{ opening_amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'pos_session_closed' => [
                'key' => 'pos_session_closed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'POS Session Closed',
                    'ar' => 'إغلاق جلسة نقطة البيع',
                ],
                'description' => [
                    'en' => 'Sent when a POS session is closed.',
                    'ar' => 'يُرسل عند إغلاق جلسة نقطة البيع.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'session_number',
                    'cashier_name',
                    'closed_at',
                    'branch_name',
                    'closing_amount',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'POS session {{ session_number }} closed',
                            'ar' => 'تم إغلاق جلسة نقطة البيع {{ session_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>POS session {{ session_number }} at {{ branch_name }} was closed by {{ cashier_name }} at {{ closed_at }}.</p><p><strong>Closing amount:</strong> {{ closing_amount }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إغلاق جلسة نقطة البيع {{ session_number }} في {{ branch_name }} بواسطة {{ cashier_name }} في {{ closed_at }}.</p><p><strong>مبلغ الإغلاق:</strong> {{ closing_amount }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'POS session {{ session_number }} closed by {{ cashier_name }}.',
                            'ar' => 'تم إغلاق جلسة نقطة البيع {{ session_number }} بواسطة {{ cashier_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'POS session {{ session_number }} closed by {{ cashier_name }}.',
                            'ar' => 'تم إغلاق جلسة نقطة البيع {{ session_number }} بواسطة {{ cashier_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'POS session {{ session_number }} closed by {{ cashier_name }}. - {{ app_name }}',
                            'ar' => 'تم إغلاق جلسة نقطة البيع {{ session_number }} بواسطة {{ cashier_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'pos_cash_variance_found' => [
                'key' => 'pos_cash_variance_found',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'POS Cash Variance Detected',
                    'ar' => 'تم اكتشاف فرق نقدية في نقطة البيع',
                ],
                'description' => [
                    'en' => 'Sent when a cash variance is detected in a POS session.',
                    'ar' => 'يُرسل عند اكتشاف فرق نقدية في جلسة نقطة البيع.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'session_number',
                    'cashier_name',
                    'branch_name',
                    'variance_amount',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Cash variance detected in session {{ session_number }}',
                            'ar' => 'تم اكتشاف فرق نقدي في الجلسة {{ session_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>A cash variance was found in session {{ session_number }} at {{ branch_name }}.</p><p><strong>Cashier:</strong> {{ cashier_name }}<br><strong>Variance:</strong> {{ variance_amount }} {{ currency }}</p><p>Please review the POS session and settlement details.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم العثور على فرق نقدي في الجلسة {{ session_number }} ضمن {{ branch_name }}.</p><p><strong>الكاشير:</strong> {{ cashier_name }}<br><strong>الفرق:</strong> {{ variance_amount }} {{ currency }}</p><p>يرجى مراجعة جلسة نقطة البيع وتفاصيل التسوية.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Cash variance detected in session {{ session_number }}.',
                            'ar' => 'تم اكتشاف فرق نقدي في الجلسة {{ session_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Cash variance detected in session {{ session_number }}.',
                            'ar' => 'تم اكتشاف فرق نقدي في الجلسة {{ session_number }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Cash variance detected in session {{ session_number }}. - {{ app_name }}',
                            'ar' => 'تم اكتشاف فرق نقدي في الجلسة {{ session_number }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'table_assigned_to_waiter' => [
                'key' => 'table_assigned_to_waiter',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Table Assigned to Waiter',
                    'ar' => 'تم تعيين الطاولة إلى نادل',
                ],
                'description' => [
                    'en' => 'Sent when a table is assigned to a waiter.',
                    'ar' => 'يُرسل عند تعيين الطاولة للنادل.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'table_name',
                    'waiter_name',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Table {{ table_name }} assigned to {{ waiter_name }}',
                            'ar' => 'تم تعيين الطاولة {{ table_name }} إلى {{ waiter_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Table {{ table_name }} has been assigned to waiter {{ waiter_name }} at {{ branch_name }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تعيين الطاولة {{ table_name }} إلى النادل {{ waiter_name }} في {{ branch_name }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, table {{ table_name }} at {{ branch_name }} assigned to {{ waiter_name }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، تم تعيين الطاولة {{ table_name }} في {{ branch_name }} إلى {{ waiter_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Table {{ table_name }} assigned to {{ waiter_name }}.',
                            'ar' => 'تم تعيين الطاولة {{ table_name }} إلى {{ waiter_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Table {{ table_name }} at {{ branch_name }} assigned to {{ waiter_name }}.',
                            'ar' => '{{ app_name }}: تم تعيين الطاولة {{ table_name }} في {{ branch_name }} إلى {{ waiter_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'table_status_changed' => [
                'key' => 'table_status_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Table Status Changed',
                    'ar' => 'تغيير حالة الطاولة',
                ],
                'description' => [
                    'en' => 'Sent when a table status changes.',
                    'ar' => 'يُرسل عند تغيير حالة الطاولة.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'table_name',
                    'table_status',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Table {{ table_name }} status changed',
                            'ar' => 'تم تغيير حالة الطاولة {{ table_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The status of table {{ table_name }} at {{ branch_name }} has changed to {{ table_status }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تغيير حالة الطاولة {{ table_name }} في {{ branch_name }} إلى {{ table_status }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Hi {{ user_name }}, table {{ table_name }} at {{ branch_name }} is now {{ table_status }}. - {{ app_name }}',
                            'ar' => 'مرحباً {{ user_name }}، أصبحت الطاولة {{ table_name }} في {{ branch_name }} الآن {{ table_status }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Table {{ table_name }} is now {{ table_status }}.',
                            'ar' => 'أصبحت الطاولة {{ table_name }} الآن {{ table_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Table {{ table_name }} at {{ branch_name }} is now {{ table_status }}.',
                            'ar' => '{{ app_name }}: أصبحت الطاولة {{ table_name }} في {{ branch_name }} الآن {{ table_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'gift_card_issued' => [
                'key' => 'gift_card_issued',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Gift Card Issued',
                    'ar' => 'إصدار بطاقة هدية',
                ],
                'description' => [
                    'en' => 'Sent when a gift card is issued.',
                    'ar' => 'يُرسل عند إصدار بطاقة هدية.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'gift_card_code',
                    'customer_name',
                    'amount',
                    'currency',
                    'expiry_date',
                    'balance',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Gift card {{ gift_card_code }} issued',
                            'ar' => 'تم إصدار بطاقة الهدايا {{ gift_card_code }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Gift card {{ gift_card_code }} has been issued for {{ customer_name }}.</p><p><strong>Initial amount:</strong> {{ amount }} {{ currency }}<br><strong>Current balance:</strong> {{ balance }} {{ currency }}<br><strong>Expiry date:</strong> {{ expiry_date }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم إصدار بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }}.</p><p><strong>المبلغ الأولي:</strong> {{ amount }} {{ currency }}<br><strong>الرصيد الحالي:</strong> {{ balance }} {{ currency }}<br><strong>تاريخ الانتهاء:</strong> {{ expiry_date }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} issued successfully.',
                            'ar' => 'تم إصدار بطاقة الهدايا {{ gift_card_code }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} issued successfully.',
                            'ar' => 'تم إصدار بطاقة الهدايا {{ gift_card_code }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} issued successfully. - {{ app_name }}',
                            'ar' => 'تم إصدار بطاقة الهدايا {{ gift_card_code }} بنجاح. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, gift card {{ gift_card_code }} was issued for {{ customer_name }}. Amount: {{ amount }} {{ currency }}. Balance: {{ balance }} {{ currency }}. Expires: {{ expiry_date }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم إصدار بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }}. المبلغ: {{ amount }} {{ currency }}. الرصيد: {{ balance }} {{ currency }}. تنتهي في: {{ expiry_date }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'gift_card_redeemed' => [
                'key' => 'gift_card_redeemed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Gift Card Redeemed',
                    'ar' => 'استخدام بطاقة هدية',
                ],
                'description' => [
                    'en' => 'Sent when a gift card is redeemed.',
                    'ar' => 'يُرسل عند استخدام بطاقة هدية.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'gift_card_code',
                    'customer_name',
                    'amount',
                    'currency',
                    'balance',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Gift card {{ gift_card_code }} redeemed',
                            'ar' => 'تم استخدام بطاقة الهدايا {{ gift_card_code }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Gift card {{ gift_card_code }} was redeemed by {{ customer_name }} for {{ amount }} {{ currency }}. Remaining balance: {{ balance }} {{ currency }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم استخدام بطاقة الهدايا {{ gift_card_code }} من قبل العميل {{ customer_name }} بمبلغ {{ amount }} {{ currency }}. الرصيد المتبقي: {{ balance }} {{ currency }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} redeemed.',
                            'ar' => 'تم استخدام بطاقة الهدايا {{ gift_card_code }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} redeemed.',
                            'ar' => 'تم استخدام بطاقة الهدايا {{ gift_card_code }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} redeemed. - {{ app_name }}',
                            'ar' => 'تم استخدام بطاقة الهدايا {{ gift_card_code }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, gift card {{ gift_card_code }} was redeemed by {{ customer_name }}. Amount: {{ amount }} {{ currency }}. Remaining balance: {{ balance }} {{ currency }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم استخدام بطاقة الهدايا {{ gift_card_code }} بواسطة {{ customer_name }}. المبلغ: {{ amount }} {{ currency }}. الرصيد المتبقي: {{ balance }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'gift_card_recharged' => [
                'key' => 'gift_card_recharged',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Gift Card Recharged',
                    'ar' => 'إعادة شحن بطاقة هدية',
                ],
                'description' => [
                    'en' => 'Sent when a gift card is recharged.',
                    'ar' => 'يُرسل عند إعادة شحن بطاقة هدية.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'gift_card_code',
                    'customer_name',
                    'amount',
                    'currency',
                    'balance',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Gift card {{ gift_card_code }} recharged',
                            'ar' => 'تم شحن بطاقة الهدايا {{ gift_card_code }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Gift card {{ gift_card_code }} for {{ customer_name }} has been recharged.</p><p><strong>Recharge amount:</strong> {{ amount }} {{ currency }}<br><strong>New balance:</strong> {{ balance }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تمت إعادة شحن بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }}.</p><p><strong>مبلغ الشحن:</strong> {{ amount }} {{ currency }}<br><strong>الرصيد الجديد:</strong> {{ balance }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} recharged successfully.',
                            'ar' => 'تم شحن بطاقة الهدايا {{ gift_card_code }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} recharged successfully.',
                            'ar' => 'تم شحن بطاقة الهدايا {{ gift_card_code }} بنجاح.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} recharged successfully. - {{ app_name }}',
                            'ar' => 'تم شحن بطاقة الهدايا {{ gift_card_code }} بنجاح. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, gift card {{ gift_card_code }} for {{ customer_name }} was recharged by {{ amount }} {{ currency }}. New balance: {{ balance }} {{ currency }}.',
                            'ar' => 'مرحباً {{ user_name }}، تمت إعادة شحن بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }} بقيمة {{ amount }} {{ currency }}. الرصيد الجديد: {{ balance }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'gift_card_adjusted' => [
                'key' => 'gift_card_adjusted',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Gift Card Adjusted',
                    'ar' => 'تعديل بطاقة هدية',
                ],
                'description' => [
                    'en' => 'Sent when a gift card is adjusted.',
                    'ar' => 'يُرسل عند تعديل بطاقة هدية.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'gift_card_code',
                    'customer_name',
                    'adjustment_amount',
                    'currency',
                    'balance',
                    'adjustment_reason',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Gift card {{ gift_card_code }} adjusted',
                            'ar' => 'تم تعديل بطاقة الهدايا {{ gift_card_code }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Gift card {{ gift_card_code }} for {{ customer_name }} has been adjusted.</p><p><strong>Adjustment:</strong> {{ adjustment_amount }} {{ currency }}<br><strong>New balance:</strong> {{ balance }} {{ currency }}<br><strong>Reason:</strong> {{ adjustment_reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تعديل بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }}.</p><p><strong>قيمة التعديل:</strong> {{ adjustment_amount }} {{ currency }}<br><strong>الرصيد الجديد:</strong> {{ balance }} {{ currency }}<br><strong>السبب:</strong> {{ adjustment_reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} adjusted.',
                            'ar' => 'تم تعديل بطاقة الهدايا {{ gift_card_code }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} adjusted.',
                            'ar' => 'تم تعديل بطاقة الهدايا {{ gift_card_code }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} adjusted. - {{ app_name }}',
                            'ar' => 'تم تعديل بطاقة الهدايا {{ gift_card_code }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, gift card {{ gift_card_code }} for {{ customer_name }} was adjusted by {{ adjustment_amount }} {{ currency }}. Balance: {{ balance }} {{ currency }}. Reason: {{ adjustment_reason }}.',
                            'ar' => 'مرحباً {{ user_name }}، تم تعديل بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }} بقيمة {{ adjustment_amount }} {{ currency }}. الرصيد: {{ balance }} {{ currency }}. السبب: {{ adjustment_reason }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'gift_card_expired' => [
                'key' => 'gift_card_expired',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Gift Card Expired',
                    'ar' => 'انتهاء بطاقة هدية',
                ],
                'description' => [
                    'en' => 'Sent when a gift card expires.',
                    'ar' => 'يُرسل عند انتهاء بطاقة هدية.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'gift_card_code',
                    'customer_name',
                    'expiry_date',
                    'balance',
                    'currency',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Gift card {{ gift_card_code }} expired',
                            'ar' => 'انتهت صلاحية بطاقة الهدايا {{ gift_card_code }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Gift card {{ gift_card_code }} for {{ customer_name }} expired on {{ expiry_date }}.</p><p><strong>Final balance:</strong> {{ balance }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>انتهت صلاحية بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }} بتاريخ {{ expiry_date }}.</p><p><strong>الرصيد النهائي:</strong> {{ balance }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} has expired.',
                            'ar' => 'انتهت صلاحية بطاقة الهدايا {{ gift_card_code }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} has expired.',
                            'ar' => 'انتهت صلاحية بطاقة الهدايا {{ gift_card_code }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Gift card {{ gift_card_code }} has expired. - {{ app_name }}',
                            'ar' => 'انتهت صلاحية بطاقة الهدايا {{ gift_card_code }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, gift card {{ gift_card_code }} for {{ customer_name }} expired on {{ expiry_date }}. Final balance: {{ balance }} {{ currency }}.',
                            'ar' => 'مرحباً {{ user_name }}، انتهت صلاحية بطاقة الهدايا {{ gift_card_code }} للعميل {{ customer_name }} بتاريخ {{ expiry_date }}. الرصيد النهائي: {{ balance }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'loyalty_points_changed' => [
                'key' => 'loyalty_points_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Loyalty Points Changed',
                    'ar' => 'تغيير نقاط الولاء',
                ],
                'description' => [
                    'en' => 'Sent when loyalty points change.',
                    'ar' => 'يُرسل عند تغيير نقاط الولاء.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'customer_name',
                    'points_change',
                    'points_balance',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Loyalty points updated for {{ customer_name }}',
                            'ar' => 'تم تحديث نقاط الولاء للعميل {{ customer_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Loyalty points for {{ customer_name }} changed by {{ points_change }}. Current balance: {{ points_balance }} points.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تغيير نقاط الولاء للعميل {{ customer_name }} بمقدار {{ points_change }}. الرصيد الحالي: {{ points_balance }} نقطة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Loyalty points updated. Current balance: {{ points_balance }}.',
                            'ar' => 'تم تحديث نقاط الولاء. الرصيد الحالي: {{ points_balance }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Loyalty points updated. Current balance: {{ points_balance }}.',
                            'ar' => 'تم تحديث نقاط الولاء. الرصيد الحالي: {{ points_balance }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Loyalty points updated. Current balance: {{ points_balance }}. - {{ app_name }}',
                            'ar' => 'تم تحديث نقاط الولاء. الرصيد الحالي: {{ points_balance }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, loyalty points for {{ customer_name }} changed by {{ points_change }}. New balance: {{ points_balance }} points.',
                            'ar' => 'مرحباً {{ user_name }}، تغيرت نقاط الولاء للعميل {{ customer_name }} بمقدار {{ points_change }}. الرصيد الجديد: {{ points_balance }} نقطة.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'loyalty_reward_redeemed' => [
                'key' => 'loyalty_reward_redeemed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Loyalty Reward Redeemed',
                    'ar' => 'استخدام مكافأة الولاء',
                ],
                'description' => [
                    'en' => 'Sent when a loyalty reward is redeemed.',
                    'ar' => 'يُرسل عند استخدام مكافأة الولاء.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'customer_name',
                    'reward_name',
                    'points_used',
                    'points_balance',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Loyalty reward redeemed by {{ customer_name }}',
                            'ar' => 'تم استبدال مكافأة ولاء من {{ customer_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>{{ customer_name }} redeemed {{ reward_name }} using {{ points_used }} points.</p><p><strong>Remaining points balance:</strong> {{ points_balance }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>قام العميل {{ customer_name }} باستبدال المكافأة {{ reward_name }} باستخدام {{ points_used }} نقطة.</p><p><strong>رصيد النقاط المتبقي:</strong> {{ points_balance }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Loyalty reward {{ reward_name }} redeemed.',
                            'ar' => 'تم استبدال مكافأة الولاء {{ reward_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Loyalty reward {{ reward_name }} redeemed.',
                            'ar' => 'تم استبدال مكافأة الولاء {{ reward_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Loyalty reward {{ reward_name }} redeemed. - {{ app_name }}',
                            'ar' => 'تم استبدال مكافأة الولاء {{ reward_name }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ customer_name }} redeemed {{ reward_name }} using {{ points_used }} points. Remaining balance: {{ points_balance }}.',
                            'ar' => 'مرحباً {{ user_name }}، استبدل {{ customer_name }} المكافأة {{ reward_name }} باستخدام {{ points_used }} نقطة. الرصيد المتبقي: {{ points_balance }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'loyalty_tier_changed' => [
                'key' => 'loyalty_tier_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Loyalty Tier Changed',
                    'ar' => 'تغيير فئة الولاء',
                ],
                'description' => [
                    'en' => 'Sent when a loyalty tier changes.',
                    'ar' => 'يُرسل عند تغيير فئة الولاء.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'customer_name',
                    'previous_tier',
                    'new_tier',
                    'points_balance',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Loyalty tier changed for {{ customer_name }}',
                            'ar' => 'تم تغيير فئة الولاء للعميل {{ customer_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>{{ customer_name }} moved from {{ previous_tier }} to {{ new_tier }}.</p><p><strong>Current points balance:</strong> {{ points_balance }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>انتقل العميل {{ customer_name }} من {{ previous_tier }} إلى {{ new_tier }}.</p><p><strong>رصيد النقاط الحالي:</strong> {{ points_balance }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Loyalty tier changed to {{ new_tier }}.',
                            'ar' => 'تم تغيير فئة الولاء إلى {{ new_tier }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Loyalty tier changed to {{ new_tier }}.',
                            'ar' => 'تم تغيير فئة الولاء إلى {{ new_tier }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Loyalty tier changed to {{ new_tier }}. - {{ app_name }}',
                            'ar' => 'تم تغيير فئة الولاء إلى {{ new_tier }}. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => [
                            'en' => 'Hello {{ user_name }}, {{ customer_name }} moved from {{ previous_tier }} to {{ new_tier }}. Points balance: {{ points_balance }}.',
                            'ar' => 'مرحباً {{ user_name }}، انتقل {{ customer_name }} من {{ previous_tier }} إلى {{ new_tier }}. رصيد النقاط: {{ points_balance }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'inventory_purchase_updated' => [
                'key' => 'inventory_purchase_updated',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Inventory Purchase Updated',
                    'ar' => 'تحديث شراء المخزون',
                ],
                'description' => [
                    'en' => 'Sent when an inventory purchase is updated.',
                    'ar' => 'يُرسل عند تحديث شراء المخزون.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'purchase_number',
                    'supplier_name',
                    'purchase_status',
                    'total_amount',
                    'currency',
                    'branch_name',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Inventory purchase {{ purchase_number }} updated',
                            'ar' => 'تم تحديث شراء المخزون {{ purchase_number }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Inventory purchase {{ purchase_number }} at {{ branch_name }} has been updated to {{ purchase_status }}.</p><p><strong>Supplier:</strong> {{ supplier_name }}<br><strong>Total:</strong> {{ total_amount }} {{ currency }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تحديث عملية شراء المخزون {{ purchase_number }} في {{ branch_name }} إلى {{ purchase_status }}.</p><p><strong>المورد:</strong> {{ supplier_name }}<br><strong>الإجمالي:</strong> {{ total_amount }} {{ currency }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Purchase {{ purchase_number }} at {{ branch_name }} is {{ purchase_status }}. Supplier: {{ supplier_name }}. Total: {{ total_amount }} {{ currency }}.',
                            'ar' => 'عملية الشراء {{ purchase_number }} في {{ branch_name }} حالتها {{ purchase_status }}. المورد: {{ supplier_name }}. الإجمالي: {{ total_amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Purchase {{ purchase_number }} is {{ purchase_status }}.',
                            'ar' => 'عملية الشراء {{ purchase_number }} حالتها {{ purchase_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Purchase {{ purchase_number }} at {{ branch_name }} is {{ purchase_status }}. Total: {{ total_amount }} {{ currency }}.',
                            'ar' => '{{ app_name }}: عملية الشراء {{ purchase_number }} في {{ branch_name }} حالتها {{ purchase_status }}. الإجمالي: {{ total_amount }} {{ currency }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'stock_transfer_status_changed' => [
                'key' => 'stock_transfer_status_changed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Stock Transfer Status Changed',
                    'ar' => 'تغيير حالة تحويل المخزون',
                ],
                'description' => [
                    'en' => 'Sent to the relevant inventory teams when a stock transfer changes workflow state.',
                    'ar' => 'يُرسل إلى فرق المخزون المعنية عند تغيير حالة تحويل المخزون.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'transfer_reference',
                    'source_branch',
                    'destination_branch',
                    'transfer_status',
                    'reason',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Stock transfer {{ transfer_reference }} is {{ transfer_status }}',
                            'ar' => 'تحويل المخزون {{ transfer_reference }} حالته {{ transfer_status }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Stock transfer {{ transfer_reference }} from {{ source_branch }} to {{ destination_branch }} is now {{ transfer_status }}.</p><p><strong>Reason:</strong> {{ reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>أصبح تحويل المخزون {{ transfer_reference }} من {{ source_branch }} إلى {{ destination_branch }} بحالة {{ transfer_status }}.</p><p><strong>السبب:</strong> {{ reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Stock transfer {{ transfer_reference }} from {{ source_branch }} to {{ destination_branch }} is {{ transfer_status }}.',
                            'ar' => 'تحويل المخزون {{ transfer_reference }} من {{ source_branch }} إلى {{ destination_branch }} حالته {{ transfer_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Transfer {{ transfer_reference }} is {{ transfer_status }}.',
                            'ar' => 'التحويل {{ transfer_reference }} حالته {{ transfer_status }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'stock_movement_needs_attention' => [
                'key' => 'stock_movement_needs_attention',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Stock Movement Needs Attention',
                    'ar' => 'حركة مخزون تحتاج إلى انتباه',
                ],
                'description' => [
                    'en' => 'Sent when a stock movement needs attention.',
                    'ar' => 'يُرسل عند وجود حركة مخزون تحتاج إلى انتباه.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'product_name',
                    'sku',
                    'branch_name',
                    'movement_type',
                    'quantity',
                    'reason',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Stock movement needs attention',
                            'ar' => 'حركة مخزون تحتاج إلى انتباه',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>Stock movement for {{ product_name }} ({{ sku }}) needs attention at {{ branch_name }}. Movement type: {{ movement_type }}. Quantity: {{ quantity }}. Reason: {{ reason }}.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>تحتاج حركة المخزون للمنتج {{ product_name }} ({{ sku }}) إلى انتباه في {{ branch_name }}. نوع الحركة: {{ movement_type }}. الكمية: {{ quantity }}. السبب: {{ reason }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Stock movement for {{ product_name }} needs attention.',
                            'ar' => 'حركة المخزون للمنتج {{ product_name }} تحتاج إلى انتباه.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Stock movement for {{ product_name }} needs attention.',
                            'ar' => 'حركة المخزون للمنتج {{ product_name }} تحتاج إلى انتباه.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => 'Stock movement for {{ product_name }} needs attention. - {{ app_name }}',
                            'ar' => 'حركة المخزون للمنتج {{ product_name }} تحتاج إلى انتباه. - {{ app_name }}',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'export_completed' => [
                'key' => 'export_completed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Export Completed',
                    'ar' => 'اكتمال التصدير',
                ],
                'description' => [
                    'en' => 'Sent when an export is completed.',
                    'ar' => 'يُرسل عند اكتمال التصدير.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'export_name',
                    'export_type',
                    'completed_at',
                    'requested_by',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Export {{ export_name }} completed',
                            'ar' => 'اكتمل التصدير {{ export_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The export {{ export_name }} of type {{ export_type }} completed successfully at {{ completed_at }}.</p><p><strong>Requested by:</strong> {{ requested_by }}</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>اكتمل التصدير {{ export_name }} من النوع {{ export_type }} بنجاح في {{ completed_at }}.</p><p><strong>تم الطلب بواسطة:</strong> {{ requested_by }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Export {{ export_name }} completed at {{ completed_at }}. Requested by {{ requested_by }}.',
                            'ar' => 'اكتمل التصدير {{ export_name }} في {{ completed_at }}. تم الطلب بواسطة {{ requested_by }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Export {{ export_name }} completed.',
                            'ar' => 'اكتمل التصدير {{ export_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Export {{ export_name }} completed at {{ completed_at }}. Requested by {{ requested_by }}.',
                            'ar' => '{{ app_name }}: اكتمل التصدير {{ export_name }} في {{ completed_at }}. تم الطلب بواسطة {{ requested_by }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
            'export_failed' => [
                'key' => 'export_failed',
                'legacy_keys' => [],
                'name' => [
                    'en' => 'Export Failed',
                    'ar' => 'فشل التصدير',
                ],
                'description' => [
                    'en' => 'Sent when an export fails.',
                    'ar' => 'يُرسل عند فشل التصدير.',
                ],
                'supported_variables' => [
                    'app_name',
                    'user_name',
                    'export_name',
                    'export_type',
                    'error_message',
                    'requested_by',
                ],
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => [
                            'en' => 'Export {{ export_name }} failed',
                            'ar' => 'فشل التصدير {{ export_name }}',
                        ],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>The export {{ export_name }} of type {{ export_type }} requested by {{ requested_by }} failed.</p><p><strong>Error:</strong> {{ error_message }}</p><p>Please review the export settings and try again if needed.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>فشل التصدير {{ export_name }} من النوع {{ export_type }} الذي طلبه {{ requested_by }}.</p><p><strong>الخطأ:</strong> {{ error_message }}</p><p>يرجى مراجعة إعدادات التصدير وإعادة المحاولة عند الحاجة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => [
                            'en' => 'Export {{ export_name }} requested by {{ requested_by }} failed. Error: {{ error_message }}.',
                            'ar' => 'فشل التصدير {{ export_name }} الذي طلبه {{ requested_by }}. الخطأ: {{ error_message }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => [
                            'en' => 'Export {{ export_name }} failed.',
                            'ar' => 'فشل التصدير {{ export_name }}.',
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: Export {{ export_name }} requested by {{ requested_by }} failed. Error: {{ error_message }}.',
                            'ar' => '{{ app_name }}: فشل التصدير {{ export_name }} الذي طلبه {{ requested_by }}. الخطأ: {{ error_message }}.',
                        ],
                        'content_type' => 'text',
                    ],
                ],
            ],
        ];

        $templates = [
            ...$templates,
            ...self::reservationTemplates(),
        ];

        return self::withRecipientRoleVariants(self::withSupportedChannels(self::withSupportedLocales($templates)));
    }

    /**
     * Get reservation notification template definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function reservationTemplates(): array
    {
        $definitions = [
            'reservation_created' => [
                'name' => ['en' => 'Reservation Created', 'ar' => 'تم إنشاء الحجز'],
                'description' => ['en' => 'Sent when a reservation is created.', 'ar' => 'يُرسل عند إنشاء حجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} created', 'ar' => 'تم إنشاء الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} for {{ customer_name }} at {{ branch_name }} on {{ reservation_date }} {{ start_time }} has been created for {{ guest_count }} guests.',
                    'ar' => 'تم إنشاء الحجز {{ reservation_number }} للعميل {{ customer_name }} في {{ branch_name }} بتاريخ {{ reservation_date }} {{ start_time }} لعدد {{ guest_count }} ضيوف.',
                ],
            ],
            'reservation_updated' => [
                'name' => ['en' => 'Reservation Updated', 'ar' => 'تم تحديث الحجز'],
                'description' => ['en' => 'Sent when reservation details are updated.', 'ar' => 'يُرسل عند تحديث تفاصيل الحجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} updated', 'ar' => 'تم تحديث الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} at {{ branch_name }} was updated. Current status: {{ status }}.',
                    'ar' => 'تم تحديث الحجز {{ reservation_number }} في {{ branch_name }}. الحالة الحالية: {{ status }}.',
                ],
            ],
            'reservation_confirmed' => [
                'name' => ['en' => 'Reservation Confirmed', 'ar' => 'تم تأكيد الحجز'],
                'description' => ['en' => 'Sent when a reservation is confirmed.', 'ar' => 'يُرسل عند تأكيد الحجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} confirmed', 'ar' => 'تم تأكيد الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} is confirmed for {{ reservation_date }} at {{ start_time }}. Table: {{ table_name }}.',
                    'ar' => 'تم تأكيد الحجز {{ reservation_number }} بتاريخ {{ reservation_date }} الساعة {{ start_time }}. الطاولة: {{ table_name }}.',
                ],
            ],
            'reservation_cancelled' => [
                'name' => ['en' => 'Reservation Cancelled', 'ar' => 'تم إلغاء الحجز'],
                'description' => ['en' => 'Sent when a reservation is cancelled.', 'ar' => 'يُرسل عند إلغاء الحجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} cancelled', 'ar' => 'تم إلغاء الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} was cancelled. Reason: {{ cancellation_reason }}.',
                    'ar' => 'تم إلغاء الحجز {{ reservation_number }}. السبب: {{ cancellation_reason }}.',
                ],
            ],
            'reservation_arrived' => [
                'name' => ['en' => 'Reservation Arrived', 'ar' => 'وصل صاحب الحجز'],
                'description' => ['en' => 'Sent when a reservation is marked as arrived.', 'ar' => 'يُرسل عند تعليم الحجز كواصل.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} arrived', 'ar' => 'وصل الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} for {{ customer_name }} has arrived. Table: {{ table_name }}.',
                    'ar' => 'وصل الحجز {{ reservation_number }} للعميل {{ customer_name }}. الطاولة: {{ table_name }}.',
                ],
            ],
            'reservation_seated' => [
                'name' => ['en' => 'Reservation Seated', 'ar' => 'تم جلوس الحجز'],
                'description' => ['en' => 'Sent when a reservation is seated.', 'ar' => 'يُرسل عند جلوس الحجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} seated', 'ar' => 'تم جلوس الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} has been seated at {{ table_name }}.',
                    'ar' => 'تم جلوس الحجز {{ reservation_number }} على {{ table_name }}.',
                ],
            ],
            'reservation_completed' => [
                'name' => ['en' => 'Reservation Completed', 'ar' => 'اكتمل الحجز'],
                'description' => ['en' => 'Sent when a reservation is completed.', 'ar' => 'يُرسل عند اكتمال الحجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} completed', 'ar' => 'اكتمل الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} at {{ branch_name }} has been completed.',
                    'ar' => 'اكتمل الحجز {{ reservation_number }} في {{ branch_name }}.',
                ],
            ],
            'reservation_no_show' => [
                'name' => ['en' => 'Reservation No-Show', 'ar' => 'عدم حضور الحجز'],
                'description' => ['en' => 'Sent when a reservation is marked as no-show.', 'ar' => 'يُرسل عند تعليم الحجز كعدم حضور.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} marked no-show', 'ar' => 'تم تعليم الحجز {{ reservation_number }} كعدم حضور'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} for {{ customer_name }} was marked as no-show.',
                    'ar' => 'تم تعليم الحجز {{ reservation_number }} للعميل {{ customer_name }} كعدم حضور.',
                ],
            ],
            'reservation_expired' => [
                'name' => ['en' => 'Reservation Expired', 'ar' => 'انتهى الحجز'],
                'description' => ['en' => 'Sent when a pending reservation expires.', 'ar' => 'يُرسل عند انتهاء حجز معلق.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} expired', 'ar' => 'انتهى الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Pending reservation {{ reservation_number }} has expired.',
                    'ar' => 'انتهى الحجز المعلق {{ reservation_number }}.',
                ],
            ],
            'reservation_rejected' => [
                'name' => ['en' => 'Reservation Rejected', 'ar' => 'تم رفض الحجز'],
                'description' => ['en' => 'Sent when a reservation is rejected.', 'ar' => 'يُرسل عند رفض الحجز.'],
                'subject' => ['en' => 'Reservation {{ reservation_number }} rejected', 'ar' => 'تم رفض الحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} was rejected. Reason: {{ reason }}.',
                    'ar' => 'تم رفض الحجز {{ reservation_number }}. السبب: {{ reason }}.',
                ],
            ],
            'reservation_table_assigned' => [
                'name' => ['en' => 'Reservation Table Assigned', 'ar' => 'تم تعيين طاولة للحجز'],
                'description' => ['en' => 'Sent when tables are assigned to a reservation.', 'ar' => 'يُرسل عند تعيين طاولات للحجز.'],
                'subject' => ['en' => 'Table assigned for {{ reservation_number }}', 'ar' => 'تم تعيين طاولة للحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reservation {{ reservation_number }} has been assigned to {{ table_name }}.',
                    'ar' => 'تم تعيين الحجز {{ reservation_number }} إلى {{ table_name }}.',
                ],
            ],
            'reservation_reminder' => [
                'name' => ['en' => 'Reservation Reminder', 'ar' => 'تذكير بالحجز'],
                'description' => ['en' => 'Sent before a reservation starts.', 'ar' => 'يُرسل قبل بدء الحجز.'],
                'subject' => ['en' => 'Reminder for reservation {{ reservation_number }}', 'ar' => 'تذكير بالحجز {{ reservation_number }}'],
                'content' => [
                    'en' => 'Reminder: reservation {{ reservation_number }} at {{ branch_name }} is scheduled for {{ reservation_date }} at {{ start_time }}.',
                    'ar' => 'تذكير: الحجز {{ reservation_number }} في {{ branch_name }} مجدول بتاريخ {{ reservation_date }} الساعة {{ start_time }}.',
                ],
            ],
            'reservation_waitlist_notified' => [
                'name' => ['en' => 'Reservation Waitlist Notified', 'ar' => 'تم إشعار قائمة انتظار الحجز'],
                'description' => ['en' => 'Sent when a waitlist customer is notified.', 'ar' => 'يُرسل عند إشعار عميل في قائمة الانتظار.'],
                'subject' => ['en' => 'Waitlist update for {{ branch_name }}', 'ar' => 'تحديث قائمة الانتظار في {{ branch_name }}'],
                'content' => [
                    'en' => '{{ customer_name }} on the waitlist for {{ preferred_date }} {{ preferred_time }} has been notified for {{ guest_count }} guests.',
                    'ar' => 'تم إشعار {{ customer_name }} في قائمة الانتظار بتاريخ {{ preferred_date }} {{ preferred_time }} لعدد {{ guest_count }} ضيوف.',
                ],
            ],
            'reservation_waitlist_converted' => [
                'name' => ['en' => 'Reservation Waitlist Converted', 'ar' => 'تم تحويل قائمة الانتظار إلى حجز'],
                'description' => ['en' => 'Sent when a waitlist entry is converted into a reservation.', 'ar' => 'يُرسل عند تحويل سجل قائمة انتظار إلى حجز.'],
                'subject' => ['en' => 'Waitlist converted to {{ reservation_number }}', 'ar' => 'تم تحويل قائمة الانتظار إلى {{ reservation_number }}'],
                'content' => [
                    'en' => 'Waitlist entry for {{ customer_name }} was converted to reservation {{ reservation_number }}.',
                    'ar' => 'تم تحويل سجل قائمة الانتظار للعميل {{ customer_name }} إلى الحجز {{ reservation_number }}.',
                ],
            ],
        ];

        $templates = [];
        foreach ($definitions as $key => $definition) {
            $templates[$key] = [
                'key' => $key,
                'legacy_keys' => [],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'supported_variables' => self::reservationSupportedVariables($key),
                'messages' => [
                    [
                        'channel_key' => 'email',
                        'subject' => $definition['subject'],
                        'content' => [
                            'en' => '<p>Hello {{ user_name }},</p><p>'.$definition['content']['en'].'</p><p>Thank you,<br>{{ app_name }} Team</p>',
                            'ar' => '<p>مرحباً {{ user_name }}،</p><p>'.$definition['content']['ar'].'</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
                        ],
                        'content_type' => 'html',
                    ],
                    [
                        'channel_key' => 'in_app',
                        'content' => $definition['content'],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'push_notification',
                        'content' => $definition['content'],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'sms',
                        'content' => [
                            'en' => '{{ app_name }}: '.$definition['content']['en'],
                            'ar' => '{{ app_name }}: '.$definition['content']['ar'],
                        ],
                        'content_type' => 'text',
                    ],
                    [
                        'channel_key' => 'whatsapp',
                        'content' => $definition['content'],
                        'content_type' => 'text',
                    ],
                ],
            ];
        }

        return $templates;
    }

    /**
     * Get reservation template variables.
     *
     * @return array<int, string>
     */
    protected static function reservationSupportedVariables(string $key): array
    {
        $variables = [
            'app_name',
            'user_name',
            'type',
            'type_id',
            'reservation_number',
            'branch_name',
            'customer_name',
            'reservation_date',
            'start_time',
            'guest_count',
            'status',
            'previous_status',
            'new_status',
            'reason',
            'cancellation_reason',
            'table_name',
        ];

        if (str_starts_with($key, 'reservation_waitlist')) {
            $variables = [
                'app_name',
                'user_name',
                'type',
                'type_id',
                'branch_name',
                'customer_name',
                'preferred_date',
                'preferred_time',
                'guest_count',
                'reservation_number',
            ];
        }

        return $variables;
    }

    /**
     * Add default and recipient-role message variants for every supported channel.
     *
     * @param  array<string, array<string, mixed>>  $templates
     * @return array<string, array<string, mixed>>
     */
    protected static function withRecipientRoleVariants(array $templates): array
    {
        foreach ($templates as $key => $template) {
            $messages = [];

            foreach ($template['messages'] ?? [] as $message) {
                $defaultMessage = [
                    ...$message,
                    'recipient_role_key' => NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE,
                    'title' => $message['title'] ?? $template['name'],
                ];
                $messages[] = $defaultMessage;

                foreach (NotificationWorkflowRecipientCatalog::allowedRolesFor($key, (string) $message['channel_key']) as $role) {
                    $messages[] = self::recipientVariantMessage($key, $template, $defaultMessage, $role);
                }
            }

            $templates[$key]['messages'] = $messages;
        }

        return $templates;
    }

    /**
     * Build one role-specific message variant from a seeded default message.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected static function recipientVariantMessage(string $templateKey, array $template, array $message, string $role): array
    {
        $channel = (string) $message['channel_key'];

        return [
            ...$message,
            'recipient_role_key' => $role,
            'title' => self::variantTitle($template['name'], $role),
            'subject' => $channel === NotificationChannelResolverService::EMAIL_CHANNEL
                ? self::variantTitle($template['name'], $role)
                : null,
            'content' => self::variantContent($templateKey, $channel, $role, $message['content']),
        ];
    }

    /**
     * Build translated variant titles.
     *
     * @param  array<string, string>  $name
     * @return array<string, string>
     */
    protected static function variantTitle(array $name, string $role): array
    {
        $titles = [];

        foreach (supportedLocaleKeys() as $locale) {
            $titles[$locale] = trim(self::localizedTemplateName($name, $locale).' - '.self::roleTitleSuffix($role, $locale));
        }

        return $titles;
    }

    /**
     * Resolve the best available template name for a locale.
     *
     * @param  array<string, string>  $name
     */
    protected static function localizedTemplateName(array $name, string $locale): string
    {
        return self::localizedTranslationValue($name, $locale)
            ?: __('notification::notification_templates.notification_template', locale: $locale);
    }

    /**
     * Resolve the best available translated value for one locale.
     *
     * @param  array<string, string>  $translations
     */
    protected static function localizedTranslationValue(array $translations, string $locale): string
    {
        $defaultLocale = setting('default_locale', 'en');
        $fallback = reset($translations);

        return (string) ($translations[$locale]
            ?? $translations[$defaultLocale]
            ?? $translations['en']
            ?? ($fallback === false ? '' : $fallback));
    }

    /**
     * Get role-specific title suffixes.
     */
    protected static function roleTitleSuffix(string $role, string $locale): string
    {
        $key = self::roleTitleSuffixTranslationKey($role);
        $translationKey = "notification::notification_templates.role_title_suffixes.{$key}";
        $suffix = __($translationKey, locale: $locale);

        if ($suffix === $translationKey) {
            return __('notification::notification_templates.role_title_suffixes.recipient', locale: $locale);
        }

        return $suffix;
    }

    /**
     * Get the translation key suffix for a recipient role.
     */
    protected static function roleTitleSuffixTranslationKey(string $role): string
    {
        return match ($role) {
            DefaultRole::Customer->value => 'customer',
            DefaultRole::Driver->value => 'driver',
            DefaultRole::Dispatcher->value => 'dispatcher',
            DefaultRole::Manager->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Admin->value => 'management',
            DefaultRole::Kitchen->value => 'kitchen',
            DefaultRole::Waiter->value => 'waiter',
            DefaultRole::Cashier->value => 'cashier',
            DefaultRole::HrManager->value,
            DefaultRole::HrOfficer->value => 'hr',
            DefaultRole::Staff->value => 'staff',
            DefaultRole::Payroll->value => 'payroll',
            DefaultRole::Inventory->value => 'inventory',
            DefaultRole::Procurement->value => 'procurement',
            DefaultRole::ExternalCourierOperator->value => 'external_courier_operator',
            default => 'recipient',
        };
    }

    /**
     * Build translated role-aware content.
     *
     * @param  array<string, string>  $content
     * @return array<string, string>
     */
    protected static function variantContent(string $templateKey, string $channel, string $role, array $content): array
    {
        $special = self::specialVariantContent($templateKey, $channel, $role);
        if ($special !== []) {
            return self::supportedLocaleTranslations($special);
        }

        $messages = [];

        foreach (supportedLocaleKeys() as $locale) {
            $intro = self::roleIntro($role, $channel, $locale);
            $baseContent = self::localizedTranslationValue($content, $locale);

            if ($channel === NotificationChannelResolverService::EMAIL_CHANNEL) {
                $greeting = __('notification::notification_templates.role_variant_email_greeting', locale: $locale);
                $messages[$locale] = "<p>$greeting</p><p>{$intro}</p>$baseContent";

                continue;
            }

            $messages[$locale] = trim("{$intro} {$baseContent}");
        }

        return $messages;
    }

    /**
     * Handwritten variants for workflows where role perspective changes the meaning.
     *
     * @return array<string, string>
     */
    protected static function specialVariantContent(string $templateKey, string $channel, string $role): array
    {
        $isEmail = $channel === NotificationChannelResolverService::EMAIL_CHANNEL;

        $managementRoles = [
            DefaultRole::Manager->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Admin->value,
            DefaultRole::Dispatcher->value,
            DefaultRole::ExternalCourierOperator->value,
        ];

        $driverFinanceRoles = [
            DefaultRole::Driver->value,
            DefaultRole::Payroll->value,
            DefaultRole::Manager->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Admin->value,
        ];

        return match (true) {
            $templateKey === 'delivery_assigned'
            && $role === DefaultRole::Driver->value
            && $isEmail => [
                'en' => '<p>Hello {{ user_name }},</p><p>A new delivery {{ delivery_number }} has been assigned to you for order {{ order_number }}.</p><p><strong>Pickup:</strong> {{ pickup_address }}<br><strong>Delivery:</strong> {{ delivery_address }}<br><strong>ETA:</strong> {{ eta }}</p><p>Please review the delivery details and start the workflow when ready.</p><p>Thank you,<br>{{ app_name }} Team</p>',
                'ar' => '<p>مرحباً {{ user_name }}،</p><p>تم تعيين توصيل جديد {{ delivery_number }} لك للطلب {{ order_number }}.</p><p><strong>الاستلام:</strong> {{ pickup_address }}<br><strong>التسليم:</strong> {{ delivery_address }}<br><strong>وقت الوصول المتوقع:</strong> {{ eta }}</p><p>يرجى مراجعة تفاصيل التوصيل وبدء سير العمل عند الجاهزية.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>',
            ],
            $templateKey === 'delivery_assigned'
            && $role === DefaultRole::Driver->value => [
                'en' => 'New delivery {{ delivery_number }} for order {{ order_number }} assigned to you. Pickup: {{ pickup_address }}. Delivery: {{ delivery_address }}. ETA: {{ eta }}.',
                'ar' => 'تم تعيين توصيل جديد {{ delivery_number }} لك للطلب {{ order_number }}. الاستلام: {{ pickup_address }}. التسليم: {{ delivery_address }}. ETA: {{ eta }}.',
            ],
            $templateKey === 'delivery_assigned'
            && $role === DefaultRole::Customer->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Your order {{ order_number }} has been assigned to {{ driver_name }}.</p><p><strong>ETA:</strong> {{ eta }}<br><strong>Delivery address:</strong> {{ delivery_address }}</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Your order {{ order_number }} has been assigned to {{ driver_name }}. ETA: {{ eta }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تم تعيين طلبك {{ order_number }} إلى {{ driver_name }}.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}<br><strong>عنوان التسليم:</strong> {{ delivery_address }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تم تعيين طلبك {{ order_number }} إلى {{ driver_name }}. ETA: {{ eta }}.',
            ],
            $templateKey === 'delivery_assigned'
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} was assigned to {{ driver_name }}.</p><p><strong>Pickup:</strong> {{ pickup_address }}<br><strong>Delivery:</strong> {{ delivery_address }}<br><strong>ETA:</strong> {{ eta }}</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Delivery {{ delivery_number }} for order {{ order_number }} assigned to {{ driver_name }}. ETA: {{ eta }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تم تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}.</p><p><strong>الاستلام:</strong> {{ pickup_address }}<br><strong>التسليم:</strong> {{ delivery_address }}<br><strong>وقت الوصول المتوقع:</strong> {{ eta }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تم تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}. ETA: {{ eta }}.',
            ],

            $templateKey === 'delivery_reassigned'
            && $role === DefaultRole::Driver->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} has been reassigned to you.</p><p><strong>ETA:</strong> {{ eta }}<br><strong>Reason:</strong> {{ reassignment_reason }}</p><p>Please review the updated delivery details.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Delivery {{ delivery_number }} for order {{ order_number }} was reassigned to you. ETA: {{ eta }}. Reason: {{ reassignment_reason }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تمت إعادة تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إليك.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}<br><strong>السبب:</strong> {{ reassignment_reason }}</p><p>يرجى مراجعة تفاصيل التوصيل المحدثة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تمت إعادة تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إليك. ETA: {{ eta }}. السبب: {{ reassignment_reason }}.',
            ],
            $templateKey === 'delivery_reassigned'
            && $role === DefaultRole::Customer->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>The driver for order {{ order_number }} has changed. The delivery is now assigned to {{ driver_name }}.</p><p><strong>ETA:</strong> {{ eta }}</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'The driver for order {{ order_number }} changed to {{ driver_name }}. ETA: {{ eta }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تم تغيير سائق الطلب {{ order_number }}. أصبح التوصيل الآن مع {{ driver_name }}.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تم تغيير سائق الطلب {{ order_number }} إلى {{ driver_name }}. ETA: {{ eta }}.',
            ],
            $templateKey === 'delivery_reassigned'
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} was reassigned to {{ driver_name }}.</p><p><strong>ETA:</strong> {{ eta }}<br><strong>Reason:</strong> {{ reassignment_reason }}</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Delivery {{ delivery_number }} reassigned to {{ driver_name }}. Reason: {{ reassignment_reason }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تمت إعادة تعيين التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ driver_name }}.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}<br><strong>السبب:</strong> {{ reassignment_reason }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تمت إعادة تعيين التوصيل {{ delivery_number }} إلى {{ driver_name }}. السبب: {{ reassignment_reason }}.',
            ],

            $templateKey === 'driver_delivery_accepted'
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>{{ driver_name }} accepted delivery {{ delivery_number }} for order {{ order_number }}.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : '{{ driver_name }} accepted delivery {{ delivery_number }} for order {{ order_number }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>قبل {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'قبل {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.',
            ],
            $templateKey === 'driver_delivery_rejected'
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>{{ driver_name }} rejected delivery {{ delivery_number }} for order {{ order_number }}.</p><p><strong>Reason:</strong> {{ rejection_reason }}</p><p>Please reassign the delivery if required.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : '{{ driver_name }} rejected delivery {{ delivery_number }} for order {{ order_number }}. Reason: {{ rejection_reason }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>رفض {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}.</p><p><strong>السبب:</strong> {{ rejection_reason }}</p><p>يرجى إعادة تعيين التوصيل عند الحاجة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'رفض {{ driver_name }} التوصيل {{ delivery_number }} للطلب {{ order_number }}. السبب: {{ rejection_reason }}.',
            ],

            $templateKey === 'delivery_status_changed'
            && $role === DefaultRole::Customer->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Your delivery {{ delivery_number }} for order {{ order_number }} is now {{ delivery_status }}.</p><p><strong>Driver:</strong> {{ driver_name }}<br><strong>ETA:</strong> {{ eta }}</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Your delivery {{ delivery_number }} for order {{ order_number }} is now {{ delivery_status }}. ETA: {{ eta }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>أصبح التوصيل {{ delivery_number }} للطلب {{ order_number }} الآن {{ delivery_status }}.</p><p><strong>السائق:</strong> {{ driver_name }}<br><strong>وقت الوصول المتوقع:</strong> {{ eta }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'أصبح التوصيل {{ delivery_number }} للطلب {{ order_number }} الآن {{ delivery_status }}. ETA: {{ eta }}.',
            ],
            $templateKey === 'delivery_status_changed'
            && $role === DefaultRole::Driver->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} is now {{ delivery_status }}.</p><p><strong>ETA:</strong> {{ eta }}</p><p>Please continue the delivery workflow as required.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Delivery {{ delivery_number }} for order {{ order_number }} is now {{ delivery_status }}. ETA: {{ eta }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>أصبح التوصيل {{ delivery_number }} للطلب {{ order_number }} الآن {{ delivery_status }}.</p><p><strong>وقت الوصول المتوقع:</strong> {{ eta }}</p><p>يرجى متابعة سير التوصيل حسب الإجراء المطلوب.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'أصبح التوصيل {{ delivery_number }} للطلب {{ order_number }} الآن {{ delivery_status }}. ETA: {{ eta }}.',
            ],
            $templateKey === 'delivery_status_changed'
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Delivery {{ delivery_number }} for order {{ order_number }} changed to {{ delivery_status }}.</p><p><strong>Driver:</strong> {{ driver_name }}<br><strong>ETA:</strong> {{ eta }}</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Delivery {{ delivery_number }} changed to {{ delivery_status }}. Driver: {{ driver_name }}. ETA: {{ eta }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تم تغيير حالة التوصيل {{ delivery_number }} للطلب {{ order_number }} إلى {{ delivery_status }}.</p><p><strong>السائق:</strong> {{ driver_name }}<br><strong>وقت الوصول المتوقع:</strong> {{ eta }}</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تم تغيير حالة التوصيل {{ delivery_number }} إلى {{ delivery_status }}. السائق: {{ driver_name }}. ETA: {{ eta }}.',
            ],

            $templateKey === 'driver_tracking_offline'
            && $role === DefaultRole::Driver->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Your location tracking appears offline.</p><p><strong>Last update:</strong> {{ last_seen_at }}<br><strong>Branch:</strong> {{ branch_name }}</p><p>Please check your internet connection, battery settings, and location permissions.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Your tracking is offline. Last update: {{ last_seen_at }}. Check connection, battery settings, and location permissions.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>يبدو أن تتبع موقعك غير متصل.</p><p><strong>آخر تحديث:</strong> {{ last_seen_at }}<br><strong>الفرع:</strong> {{ branch_name }}</p><p>يرجى التحقق من الاتصال بالإنترنت وإعدادات البطارية وصلاحيات الموقع.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تتبع موقعك غير متصل. آخر تحديث: {{ last_seen_at }}. تحقق من الاتصال والبطارية وصلاحيات الموقع.',
            ],
            in_array($templateKey, ['driver_tracking_stale', 'driver_tracking_offline', 'driver_tracking_permission_issue', 'driver_tracking_token_issue'], true)
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>A driver tracking issue requires review for {{ driver_name }} in {{ branch_name }}.</p><p>Please open the driver tracking screen and check the latest device, permission, and session status.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Driver tracking issue for {{ driver_name }} in {{ branch_name }}. Please review the tracking details.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>توجد مشكلة تتبع تحتاج إلى مراجعة للسائق {{ driver_name }} في {{ branch_name }}.</p><p>يرجى فتح شاشة تتبع السائق والتحقق من حالة الجهاز والصلاحيات والجلسة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'مشكلة تتبع للسائق {{ driver_name }} في {{ branch_name }}. يرجى مراجعة تفاصيل التتبع.',
            ],

            in_array($templateKey, ['driver_cash_settlement_updated', 'driver_earnings_updated', 'driver_payout_updated', 'driver_payout_issue_reported', 'driver_payout_issue_resolved'], true)
            && in_array($role, $driverFinanceRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>A driver finance update is available for {{ driver_name }}.</p><p>Please review the finance details in {{ app_name }} to confirm the latest status.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Driver finance update for {{ driver_name }}. Please review the latest details in {{ app_name }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>يوجد تحديث مالي للسائق {{ driver_name }}.</p><p>يرجى مراجعة التفاصيل المالية في {{ app_name }} للتأكد من آخر حالة.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تحديث مالي للسائق {{ driver_name }}. يرجى مراجعة آخر التفاصيل في {{ app_name }}.',
            ],

            $templateKey === 'order_status_changed'
            && $role === DefaultRole::Customer->value => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Your order {{ order_number }} at {{ branch_name }} is now {{ order_status }}.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Your order {{ order_number }} is now {{ order_status }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>أصبح طلبك {{ order_number }} في {{ branch_name }} الآن {{ order_status }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'أصبح طلبك {{ order_number }} الآن {{ order_status }}.',
            ],
            $templateKey === 'order_status_changed'
            && in_array($role, [DefaultRole::Waiter->value, DefaultRole::Kitchen->value], true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Order {{ order_number }} at {{ branch_name }} moved to {{ order_status }}. Please continue the service workflow as required.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Order {{ order_number }} moved to {{ order_status }}. Continue the service workflow as required.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>انتقل الطلب {{ order_number }} في {{ branch_name }} إلى {{ order_status }}. يرجى متابعة سير الخدمة حسب الإجراء المطلوب.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'انتقل الطلب {{ order_number }} إلى {{ order_status }}. يرجى متابعة سير الخدمة حسب الإجراء المطلوب.',
            ],
            $templateKey === 'order_status_changed'
            && in_array($role, $managementRoles, true) => [
                'en' => $isEmail
                    ? '<p>Hello {{ user_name }},</p><p>Order {{ order_number }} at {{ branch_name }} changed to {{ order_status }}.</p><p>Thank you,<br>{{ app_name }} Team</p>'
                    : 'Order {{ order_number }} at {{ branch_name }} changed to {{ order_status }}.',
                'ar' => $isEmail
                    ? '<p>مرحباً {{ user_name }}،</p><p>تم تغيير حالة الطلب {{ order_number }} في {{ branch_name }} إلى {{ order_status }}.</p><p>شكراً لك،<br>فريق {{ app_name }}</p>'
                    : 'تم تغيير حالة الطلب {{ order_number }} في {{ branch_name }} إلى {{ order_status }}.',
            ],

            default => [],
        };
    }

    /**
     * Get role-specific opening text.
     */
    protected static function roleIntro(string $role, string $channel, string $locale): string
    {
        $suffix = $channel === NotificationChannelResolverService::EMAIL_CHANNEL
            ? ''
            : ' - {{ app_name }}';

        $key = match ($role) {
            DefaultRole::Customer->value => 'customer',
            DefaultRole::Driver->value => 'driver',
            DefaultRole::Dispatcher->value,
            DefaultRole::ExternalCourierOperator->value => 'operations',
            DefaultRole::Manager->value,
            DefaultRole::AdminBranch->value,
            DefaultRole::Admin->value => 'management',
            DefaultRole::Kitchen->value,
            DefaultRole::Waiter->value,
            DefaultRole::Cashier->value,
            DefaultRole::Inventory->value,
            DefaultRole::Procurement->value,
            DefaultRole::Payroll->value => 'team',
            DefaultRole::HrManager->value,
            DefaultRole::HrOfficer->value => 'hr',
            DefaultRole::Staff->value => 'staff',
            default => 'recipient',
        };

        return __("notification::notification_templates.role_intros.{$key}", [
            'suffix' => $suffix,
        ], locale: $locale);
    }

    /**
     * Filter each template message list to its supported channel policy.
     *
     * @param  array<string, array<string, mixed>>  $templates
     * @return array<string, array<string, mixed>>
     */
    protected static function withSupportedChannels(array $templates): array
    {
        foreach ($templates as $key => $template) {
            $channels = self::CHANNELS[$key] ?? self::messageChannelKeys($template);

            $templates[$key]['messages'] = array_values(array_filter(
                $template['messages'] ?? [],
                fn (array $message) => in_array($message['channel_key'], $channels, true)
            ));
        }

        return $templates;
    }

    /**
     * Get channel keys from a template message list.
     *
     * @param  array<string, mixed>  $template
     * @return array<int, string>
     */
    public static function messageChannelKeys(array $template): array
    {
        return array_values(array_unique(array_map(
            fn (array $message) => (string) $message['channel_key'],
            $template['messages'] ?? []
        )));
    }

    /**
     * Expand all seeded translations to the configured supported locales.
     *
     * @param  array<string, array<string, mixed>>  $templates
     * @return array<string, array<string, mixed>>
     */
    protected static function withSupportedLocales(array $templates): array
    {
        foreach ($templates as $key => $template) {
            $templates[$key]['name'] = self::supportedLocaleTranslations($template['name'] ?? []);
            $templates[$key]['description'] = self::supportedLocaleTranslations($template['description'] ?? []);

            foreach ($template['messages'] ?? [] as $messageIndex => $message) {
                foreach (['title', 'subject', 'content'] as $field) {
                    if (isset($message[$field]) && is_array($message[$field])) {
                        $templates[$key]['messages'][$messageIndex][$field] = self::supportedLocaleTranslations($message[$field]);
                    }
                }
            }
        }

        return $templates;
    }

    /**
     * Build a translation array for every supported locale.
     *
     * @param  array<string, string>  $translations
     * @return array<string, string>
     */
    protected static function supportedLocaleTranslations(array $translations): array
    {
        $localized = [];

        foreach (supportedLocaleKeys() as $locale) {
            $localized[$locale] = self::localizedTranslationValue($translations, $locale);
        }

        return $localized;
    }
}
