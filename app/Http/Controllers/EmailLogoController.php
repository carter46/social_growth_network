<?php

namespace App\Http\Controllers;

use App\Services\Branding\EmailLogoResolver;
use App\Services\Branding\SiteBrandingRepository;
use Illuminate\Http\Response;

class EmailLogoController extends Controller
{
    public function __invoke(SiteBrandingRepository $branding, EmailLogoResolver $resolver): Response
    {
        $mediaId = $branding->all()['logo_light_media_id'] ?? null;
        $logo = $resolver->logo($mediaId ? (int) $mediaId : null);

        abort_if($logo === null, 404);

        return response($logo['bytes'], 200, [
            'Content-Type' => $logo['mime'],
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
