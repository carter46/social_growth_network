@php
    $siteName = $branding['site_name'] ?? config('app.name');
    $logoUrl = absolute_media_url_from_id($branding['logo_light_media_id'] ?? null, null, 'medium');
    $siteUrl = config('app.url');
    $unsubscribeUrl = \Illuminate\Support\Facades\Route::has('dashboard.notifications')
        ? route('dashboard.notifications')
        : $siteUrl;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $message->emailSubject ?: $message->title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:24px 28px;border-bottom:1px solid #e5e7eb;background:#004AC6;color:#ffffff;">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height:40px;max-width:180px;display:block;margin-bottom:8px;">
                        @endif
                        <div style="font-size:18px;font-weight:700;line-height:1.3;">{{ $siteName }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#111827;">{{ $message->title }}</h1>
                        @if($message->body)
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">{{ $message->body }}</p>
                        @endif
                        @isset($context['order_reference'])
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;font-size:14px;">
                                <tr><td style="padding:4px 0;color:#6b7280;">Order</td><td style="padding:4px 0;font-weight:600;">{{ $context['order_reference'] }}</td></tr>
                                @if(!empty($context['total_amount']))
                                    <tr><td style="padding:4px 0;color:#6b7280;">Amount</td><td style="padding:4px 0;font-weight:600;">₦{{ number_format((float) $context['total_amount'], 2) }}</td></tr>
                                @endif
                                @if(!empty($context['buyer_name']))
                                    <tr><td style="padding:4px 0;color:#6b7280;">Customer</td><td style="padding:4px 0;">{{ $context['buyer_name'] }}</td></tr>
                                @endif
                            </table>
                        @endisset
                        @if($message->actionUrl)
                            <p style="margin:20px 0 0;">
                                <a href="{{ $message->actionUrl }}" style="display:inline-block;background:#004AC6;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;font-size:14px;">View details</a>
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px;border-top:1px solid #e5e7eb;background:#f9fafb;font-size:12px;line-height:1.6;color:#6b7280;">
                        <div style="font-weight:600;color:#374151;margin-bottom:6px;">{{ $siteName }}</div>
                        <div><a href="{{ $siteUrl }}" style="color:#004AC6;text-decoration:none;">{{ $siteUrl }}</a></div>
                        @if(!empty($contact['email_support']))
                            <div style="margin-top:8px;">Support: <a href="mailto:{{ $contact['email_support'] }}" style="color:#004AC6;">{{ $contact['email_support'] }}</a></div>
                        @endif
                        <div style="margin-top:12px;">
                            <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Notification preferences</a>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
