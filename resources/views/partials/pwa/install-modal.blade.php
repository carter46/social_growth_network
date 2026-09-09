<div
    id="pwa-install-modal"
    class="hidden fixed inset-0 z-[100] items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pwa-install-modal-title"
>
    <div class="relative w-full max-w-md glassmorphism rounded-2xl border border-white/10 p-6 sm:p-8 shadow-2xl">
        <button
            type="button"
            id="pwa-install-modal-close"
            class="absolute top-4 right-4 p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
            aria-label="Close"
        >
            <x-ui.icon name="close" class="w-5 h-5" />
        </button>

        <h2 id="pwa-install-modal-title" class="text-xl font-bold font-display mb-2 pr-8">Install {{ $siteName ?? config('app.name') }}</h2>
        <p class="text-slate-400 text-sm mb-6">Add the app to your home screen or desktop for quick access.</p>

        <div data-pwa-panel="ios" class="hidden space-y-4 text-sm text-slate-300">
            <p class="font-semibold text-white">On iPhone or iPad (Safari):</p>
            <ol class="list-decimal list-inside space-y-2 text-slate-400">
                <li>Tap the <strong class="text-slate-200">Share</strong> button in Safari.</li>
                <li>Scroll and tap <strong class="text-slate-200">Add to Home Screen</strong>.</li>
                <li>Tap <strong class="text-slate-200">Add</strong> to confirm.</li>
            </ol>
        </div>

        <div data-pwa-panel="mobile" class="hidden space-y-4 text-sm text-slate-300">
            <p class="font-semibold text-white">On Android (Chrome):</p>
            <ol class="list-decimal list-inside space-y-2 text-slate-400">
                <li>Open the browser menu (three dots).</li>
                <li>Tap <strong class="text-slate-200">Install app</strong> or <strong class="text-slate-200">Add to Home screen</strong>.</li>
                <li>Confirm when prompted.</li>
            </ol>
            <p class="text-xs text-slate-500">If you already dismissed the install banner, try refreshing this page and tap Download Mobile App again.</p>
        </div>

        <div data-pwa-panel="desktop" class="hidden space-y-4 text-sm text-slate-300">
            <p class="font-semibold text-white">On desktop (Chrome, Edge, or Brave):</p>
            <ol class="list-decimal list-inside space-y-2 text-slate-400">
                <li>Look for the install icon in the address bar, or open the browser menu.</li>
                <li>Choose <strong class="text-slate-200">Install {{ $siteName ?? config('app.name') }}</strong>.</li>
                <li>Launch it from your apps or taskbar like any desktop app.</li>
            </ol>
        </div>
    </div>
</div>
