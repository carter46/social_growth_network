<?php

namespace App\Http\Controllers;

use App\Models\PlatformProduct;
use App\Support\PlatformProductSlugRedirect;
use Illuminate\Http\RedirectResponse;

/**
 * Old public /services URLs: a product link opens that product in the member catalog,
 * anything else opens the catalog. Guests are sent to log in by the dashboard's auth middleware.
 */
class LegacyServicePageRedirectController extends Controller
{
    public function __invoke(?string $path = null): RedirectResponse
    {
        $segments = array_reverse(array_values(array_filter(explode('/', (string) $path))));

        foreach ($segments as $segment) {
            $slug = PlatformProductSlugRedirect::canonical($segment);
            if (PlatformProduct::query()->visibleToPublic()->where('slug', $slug)->exists()) {
                return redirect()->route('dashboard.services.product', $slug, 301);
            }
        }

        return redirect()->route('dashboard.services', status: 301);
    }
}
