<?php

namespace App\Http\Controllers;

use App\Support\HelpContent;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = Cache::remember('sitemap.xml.v3', now()->addHour(), fn () => $this->buildUrls());

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Only public marketing pages: service pages live behind login in the member dashboard.
     *
     * @return list<array{loc: string, lastmod?: string, priority: string, changefreq: string}>
     */
    private function buildUrls(): array
    {
        $urls = [];

        $staticRoutes = [
            'home' => ['priority' => '1.0', 'changefreq' => 'daily'],
            'about' => ['priority' => '0.5', 'changefreq' => 'monthly'],
            'agents' => ['priority' => '0.6', 'changefreq' => 'monthly'],
            'help' => ['priority' => '0.5', 'changefreq' => 'monthly'],
            'contact' => ['priority' => '0.5', 'changefreq' => 'monthly'],
            'legal' => ['priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        foreach ($staticRoutes as $name => $meta) {
            if (Route::has($name)) {
                $this->push($urls, route($name), $meta);
            }
        }

        $this->push($urls, route('legal', ['doc' => 'terms']), ['priority' => '0.3', 'changefreq' => 'yearly']);
        $this->push($urls, route('legal', ['doc' => 'privacy']), ['priority' => '0.3', 'changefreq' => 'yearly']);

        try {
            foreach (array_keys(HelpContent::all()) as $helpSlug) {
                $this->push($urls, route('help.article', $helpSlug), [
                    'priority' => '0.45',
                    'changefreq' => 'monthly',
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('sitemap.help_failed', ['message' => $e->getMessage()]);
        }

        return $urls;
    }

    /**
     * @param  list<array{loc: string, lastmod?: string, priority: string, changefreq: string}>  $urls
     * @param  array{lastmod?: mixed, priority?: string, changefreq?: string}  $meta
     */
    private function push(array &$urls, string $loc, array $meta = []): void
    {
        if ($loc === '' || ! str_starts_with($loc, 'http')) {
            return;
        }

        $entry = [
            'loc' => $loc,
            'priority' => $meta['priority'] ?? '0.5',
            'changefreq' => $meta['changefreq'] ?? 'weekly',
        ];

        if (! empty($meta['lastmod'])) {
            $lastmod = $this->formatLastmod($meta['lastmod']);
            if ($lastmod !== null) {
                $entry['lastmod'] = $lastmod;
            }
        }

        $urls[] = $entry;
    }

    private function formatLastmod(mixed $value): ?string
    {
        try {
            if ($value instanceof CarbonInterface) {
                return $value->toAtomString();
            }

            return Carbon::parse($value)->toAtomString();
        } catch (Throwable) {
            return null;
        }
    }
}
