<?php

namespace App\ViewModels;

use App\Services\Branding\SiteBrandingRepository;
use App\Services\Communications\Contact\PlatformContactRepository;
use App\Services\Communications\Social\SocialLinkRepository;
use Illuminate\Support\Collection;

class FooterViewModel
{
    public function __construct(
        public readonly string $siteName,
        public readonly string $tagline,
        public readonly string $metaDescription,
        public readonly ?string $logoDarkUrl,
        public readonly ?string $logoLightUrl,
        public readonly Collection $socialLinks,
        public readonly array $contact,
    ) {}

    public static function make(): self
    {
        $branding = app(SiteBrandingRepository::class)->all();
        $contact = app(PlatformContactRepository::class)->all();
        $socials = app(SocialLinkRepository::class)->enabled();

        $logoDark = $branding['logo_dark_media_id']
            ? media_url_from_id($branding['logo_dark_media_id'])
            : asset('assets/images/white_originla_logo.png');
        $logoLight = $branding['logo_light_media_id']
            ? media_url_from_id($branding['logo_light_media_id'])
            : asset('assets/images/originla_logo.png');

        return new self(
            siteName: $branding['site_name'],
            tagline: $branding['tagline'] ?: 'Social media growth and engagement services with clear deliverables and secure checkout.',
            metaDescription: $branding['meta_description'],
            logoDarkUrl: $logoDark,
            logoLightUrl: $logoLight,
            socialLinks: $socials,
            contact: $contact,
        );
    }
}
