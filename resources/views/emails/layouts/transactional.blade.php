@php
    $brandingRepo = app(\App\Services\Branding\SiteBrandingRepository::class);
    $contactRepo = app(\App\Services\Communications\Contact\PlatformContactRepository::class);
    $siteName = $brandingRepo->all()['site_name'] ?? config('app.name');
    $logoUrl = absolute_media_url_from_id($brandingRepo->all()['logo_light_media_id'] ?? null, null, 'medium');
    $siteUrl = absolute_url(config('app.url')) ?? config('app.url');
    $supportEmail = $contactRepo->all()['email_support'] ?? null;
    $pageTitle = trim($__env->yieldContent('title')) ?: $siteName;
    $heading = trim($__env->yieldContent('heading')) ?: $pageTitle;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
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
                        <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111827;">{{ $heading }}</h1>
                        <div style="font-size:15px;line-height:1.6;color:#374151;">
                            @yield('content')
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px;border-top:1px solid #e5e7eb;background:#f9fafb;font-size:12px;line-height:1.6;color:#6b7280;">
                        <div style="font-weight:600;color:#374151;margin-bottom:6px;">{{ $siteName }}</div>
                        <div><a href="{{ $siteUrl }}" style="color:#004AC6;text-decoration:none;">{{ $siteUrl }}</a></div>
                        @if($supportEmail)
                            <div style="margin-top:8px;">Support: <a href="mailto:{{ $supportEmail }}" style="color:#004AC6;">{{ $supportEmail }}</a></div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
