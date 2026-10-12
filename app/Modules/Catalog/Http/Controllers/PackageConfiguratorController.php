<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformProduct;
use App\Modules\Catalog\Services\CatalogBrowseService;
use App\Services\Campaigns\CampaignBriefException;
use App\Services\Catalog\PackageLinkValidator;
use App\Services\Catalog\PackageQuoteException;
use App\Services\Catalog\PackageQuoteService;
use App\Services\Catalog\PurchaseDraftService;
use App\Services\Engagement\YoutubeLookupService;
use App\Support\PlatformProductSlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public package form: checks the package, keeps it through login or sign-up,
 * then hands it to the dashboard checkout.
 */
class PackageConfiguratorController extends Controller
{
    public const CREATOR_ONLY_MESSAGE = 'Buying needs a Creator account. Log in with your Creator account, or sign up as a Creator, to finish this order.';

    public function __construct(
        private PackageQuoteService $quotes,
        private PackageLinkValidator $links,
        private PurchaseDraftService $drafts,
        private YoutubeLookupService $lookup,
        private CatalogBrowseService $browse,
    ) {}

    public function configure(Request $request, string $slug): RedirectResponse
    {
        $product = $this->findProduct($slug);
        $user = $request->user();

        if ($user && ! $user->isCreator()) {
            return back()->withInput()->with('error', self::CREATOR_ONLY_MESSAGE);
        }

        if (! $product->isPurchasable()) {
            return back()->withInput()->with('error', 'This product is not available to buy yet. Please try again later.');
        }

        $data = $request->validate([
            'units' => ['required'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable'],
            'target_url' => ['nullable', 'string', 'max:2048'],
            'channel_url' => ['nullable', 'string', 'max:500'],
            'comment_instructions' => ['nullable', 'string', 'max:'.\App\Services\Campaigns\CampaignBriefService::COMMENT_INSTRUCTIONS_MAX],
        ]);

        try {
            $quote = $this->quotes->quote($product, $data['units'], (array) ($data['qty'] ?? []));
        } catch (PackageQuoteException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        $linkInput = [
            'target_url' => (string) ($data['target_url'] ?? ''),
            'channel_url' => (string) ($data['channel_url'] ?? ''),
            'comment_instructions' => (string) ($data['comment_instructions'] ?? ''),
        ];

        try {
            $this->links->validate($product, $linkInput);
        } catch (CampaignBriefException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        $companions = array_diff_key($quote['task_quantities'], array_flip(array_filter([$quote['main_task']])));
        $this->drafts->put($product->slug, $quote['main_qty'], $companions, $linkInput);

        $resume = route('dashboard.services.resume', $product->slug);

        if (! $user) {
            $request->session()->put('url.intended', $resume);

            return redirect()
                ->route('login')
                ->with('status', 'Your package is saved. Log in or create a Creator account to finish your order.');
        }

        return redirect()->to($resume);
    }

    public function resume(Request $request, string $slug): RedirectResponse
    {
        $slug = PlatformProductSlugRedirect::canonical($slug);
        $product = PlatformProduct::query()->visibleToPublic()->where('slug', $slug)->first();

        if (! $request->user()->isCreator()) {
            $target = $product ? $this->browse->productUrl($product) : route('services');

            return redirect()->to($target)->with('error', self::CREATOR_ONLY_MESSAGE);
        }

        if (! $product) {
            $this->drafts->clear($slug);

            return redirect()->route('dashboard.services')->with('error', 'That service is no longer available.');
        }

        $draft = $this->drafts->get($product->slug);
        if ($draft === null) {
            return redirect()
                ->route('dashboard.services.product', $product->slug)
                ->with('error', 'Your saved package has expired. Please set it up again.');
        }

        return redirect()->route('dashboard.services.checkout', array_merge(
            ['slug' => $product->slug],
            PurchaseDraftService::checkoutQuery($draft),
        ));
    }

    public function videoPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'product_slug' => ['required', 'string', 'max:120'],
        ]);
        $product = $this->findProduct($data['product_slug']);
        $result = $this->lookup->video($data['url'], $product->taskProfile()->tasks());

        return response()->json($result + ['allowed' => YoutubeLookupService::allowsPurchase($result['status'])]);
    }

    public function channelPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'product_slug' => ['required', 'string', 'max:120'],
        ]);
        $product = $this->findProduct($data['product_slug']);
        $result = $this->lookup->channel($data['url'], $product->taskProfile()->tasks());

        return response()->json($result + ['allowed' => YoutubeLookupService::allowsPurchase($result['status'])]);
    }

    private function findProduct(string $slug): PlatformProduct
    {
        return PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', PlatformProductSlugRedirect::canonical($slug))
            ->with('activeVariants')
            ->firstOrFail();
    }
}
