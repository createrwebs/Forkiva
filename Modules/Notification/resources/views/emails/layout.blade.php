@php
    $brand = app(\Modules\Notification\Support\EmailBranding::class);
    $brandName = $brand->name();
    $emailLocale = str_replace('_', '-', app()->getLocale());
    $rtl = in_array(explode('-', $emailLocale)[0], ['ar', 'fa', 'he', 'ur'], true);
    $direction = $rtl ? 'rtl' : 'ltr';
    $alignment = $rtl ? 'right' : 'left';
    $headingFont = $rtl ? 'Tahoma, Arial, sans-serif' : 'Georgia, "Times New Roman", serif';
@endphp
<!doctype html>
<html lang="{{ $emailLocale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title ?? $brandName }}</title>
    <style>
        body, table, td, p, a, li { font-family: Arial, Tahoma, sans-serif; }
        table { border-spacing: 0; }
        .email-body h1 { margin: 0 0 28px; color: #172f3e; font-family: {!! $headingFont !!}; font-size: {{ $rtl ? '36px' : '44px' }}; font-weight: 400; line-height: 1.14; letter-spacing: {{ $rtl ? '0' : '-1.4px' }}; text-align: {{ $alignment }}; }
        .email-body h2 { margin: 30px 0 16px; color: #172f3e; font-size: 22px; font-weight: 600; line-height: 1.4; text-align: {{ $alignment }}; }
        .email-body p { margin: 0 0 20px; color: #53616a; font-size: 16px; line-height: 1.8; text-align: {{ $alignment }}; }
        .email-body strong { color: #172f3e; }
        .email-body ul, .email-body ol { margin: 26px 0; padding: {{ $rtl ? '20px 34px 20px 20px' : '20px 20px 20px 34px' }}; border-top: 1px solid #e8ecec; border-bottom: 1px solid #e8ecec; }
        .email-body li { padding: 5px 0; color: #53616a; font-size: 16px; line-height: 1.7; text-align: {{ $alignment }}; }
        .email-body a { color: #174d70; text-decoration: underline; }
        .email-body .action { margin: 32px auto; }
        .email-body .button { border: 15px solid #172f3e; border-right-width: 28px; border-left-width: 28px; border-radius: 6px; background-color: #172f3e; color: #ffffff; font-size: 15px; font-weight: 600; line-height: 1.5; text-decoration: none; }
        .email-body .button-error { border-color: #b42318; background-color: #b42318; }
        .email-body .button-success { border-color: #18794e; background-color: #18794e; }
        .email-body .panel { margin: 28px 0; }
        .email-body .panel-content { padding: 24px; border: 1px solid #e8ecec; background-color: #f7f8f7; }
        .email-body .subcopy { margin-top: 30px; padding-top: 24px; border-top: 1px solid #e8ecec; }
        .email-body .subcopy p { font-size: 12px; line-height: 1.7; }
        @media only screen and (max-width: 600px) {
            .email-outer { padding: 24px 12px !important; }
            .email-card { width: 100% !important; }
            .email-header { padding: 28px 26px 0 !important; }
            .email-body { padding: 28px 26px 30px !important; }
            .email-body h1 { font-size: {{ $rtl ? '29px' : '36px' }} !important; letter-spacing: {{ $rtl ? '0' : '-0.8px' }} !important; }
            .email-category { padding: 30px 26px 0 !important; }
            .email-code-value { font-size: 32px !important; letter-spacing: 6px !important; }
            .email-code-cell { padding: 24px 12px !important; }
            .email-footer { padding-right: 14px !important; padding-left: 14px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f1f3f2; color: #53616a; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; font-size: 1px; line-height: 1px; mso-hide: all;">{{ $title ?? $brandName }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f1f3f2;">
        <tr>
            <td class="email-outer" align="center" style="padding: 48px 20px;">
                <!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
                <table class="email-card" role="presentation" width="600" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 600px; margin: 0 auto;">
                    <tr>
                        <td bgcolor="#ffffff" style="border: 1px solid #e4e8e7; border-top: 3px solid #f49a31; background-color: #ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="email-header" style="padding: 34px 48px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $direction }}" style="border-bottom: 1px solid #e8ecec;">
                                            <tr>
                                                <td width="52" valign="middle" style="width: 52px; padding-bottom: 28px;">
                                                    <img src="{{ $brand->logo($message ?? null) }}" width="52" height="52" alt="{{ $brandName }}" style="display: block; width: 52px; height: 52px; object-fit: contain; border: 0;">
                                                </td>
                                                <td align="{{ $alignment }}" valign="middle" style="padding: 0 12px 28px; text-align: {{ $alignment }}; overflow-wrap: anywhere; word-break: break-word;">
                                                    <p style="margin: 0; color: #172f3e; font-size: 23px; font-weight: 700; line-height: 1.3; letter-spacing: {{ $rtl ? '0' : '-0.6px' }}; text-align: {{ $alignment }};">{{ $brandName }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-category" align="{{ $alignment }}" style="padding: 38px 48px 0; color: #a65c16; font-size: 10px; font-weight: 700; letter-spacing: {{ $rtl ? '0' : '1.8px' }}; line-height: 1.5; text-transform: uppercase; text-align: {{ $alignment }};">{{ $category ?? __('notification::email.notification') }}</td>
                                </tr>
                                <tr>
                                    <td class="email-body" dir="{{ $direction }}" align="{{ $alignment }}" style="padding: 16px 48px 38px; font-size: 16px; line-height: 1.8; text-align: {{ $alignment }}; overflow-wrap: anywhere; word-break: break-word;">
                                        {!! $emailBody !!}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-footer" align="center" dir="{{ $direction }}" style="padding: 25px 42px 0; text-align: center;">
                            <p style="margin: 0 0 7px; color: #6e7b82; font-size: 11px; line-height: 1.7; text-align: center;">{{ __('notification::email.automated', ['app' => $brandName]) }}</p>
                            <p style="margin: 0; color: #6e7b82; font-size: 11px; line-height: 1.7; text-align: center;">&copy; {{ date('Y') }} {{ $brandName }}. {{ __('notification::email.rights') }}</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
