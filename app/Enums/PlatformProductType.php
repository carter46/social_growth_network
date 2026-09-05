<?php

namespace App\Enums;

enum PlatformProductType: string
{
    case SocialService = 'social_service';

    public function label(): string
    {
        return config('catalog.types.'.$this->value.'.label', str_replace('_', ' ', ucfirst($this->value)));
    }

    public function icon(): string
    {
        return config('catalog.types.'.$this->value.'.icon', 'grid');
    }

    public function defaultRoute(): string
    {
        return config('catalog.types.'.$this->value.'.default_route', 'services');
    }
}
