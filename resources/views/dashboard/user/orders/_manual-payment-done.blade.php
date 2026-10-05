<div class="space-y-3">
    <p class="text-sm text-text-secondary">Once your transfer is confirmed, the order is activated and any campaign in it goes live for agents. You can follow it from My Orders.</p>
    <div class="flex flex-col gap-2 sm:flex-row">
        <x-dashboard.button :href="route('dashboard')" variant="primary" class="w-full sm:w-auto">Done</x-dashboard.button>
        <x-dashboard.button :href="route('dashboard.service-orders')" variant="secondary" class="w-full sm:w-auto">View my orders</x-dashboard.button>
    </div>
</div>
