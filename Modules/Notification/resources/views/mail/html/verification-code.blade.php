@props(['minutes'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; margin: 28px 0; border: 1px solid #efdac0; border-radius: 8px; background-color: #fff7ec;">
<tr>
<td class="email-code-cell" align="center" style="padding: 27px 20px; text-align: center;">
<div style="color: #8c561b; font-size: 11px; font-weight: 700; line-height: 1.5; text-align: center;">{{ __('notification::email.verification_code') }}</div>
<div class="email-code-value" dir="ltr" style="margin: 12px 0; color: #172f3e; font-family: 'Courier New', Courier, monospace; font-size: 42px; font-weight: 700; letter-spacing: 10px; line-height: 1.3; text-align: center; white-space: nowrap;">{{ trim($slot) }}</div>
<div style="color: #816951; font-size: 12px; line-height: 1.5; text-align: center;">{{ __('notification::email.expires', ['minutes' => $minutes]) }}</div>
</td>
</tr>
</table>
