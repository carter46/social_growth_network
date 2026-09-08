@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Support')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
@php
    $contact = $contact ?? [];
    $emails = array_filter([
        'Support' => $contact['email_support'] ?? null,
        'Sales' => $contact['email_sales'] ?? null,
        'Info' => $contact['email_info'] ?? null,
    ]);
    $phones = array_filter([
        'Phone' => $contact['phone_support'] ?? null,
        'General' => $contact['phone_general'] ?? null,
        'WhatsApp' => $contact['phone_whatsapp'] ?? null,
    ]);
@endphp
<x-layout.page
    title="Support Center"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Support', null],
    ]"
>
    <div class="space-y-6">
        <x-dashboard.card>
            <h2 class="text-base font-semibold text-text-primary mb-3">Contact information</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                @forelse ($emails as $label => $email)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-text-muted">{{ $label }}</p>
                        <a href="mailto:{{ $email }}" class="text-primary font-medium hover:underline">{{ $email }}</a>
                    </div>
                @empty
                    <p class="text-text-secondary">Email contacts will appear once configured by an admin.</p>
                @endforelse
                @foreach ($phones as $label => $phone)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-text-muted">{{ $label }}</p>
                        @if ($label === 'WhatsApp')
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" class="text-primary font-medium hover:underline" target="_blank" rel="noopener">{{ $phone }}</a>
                        @else
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="text-primary font-medium hover:underline">{{ $phone }}</a>
                        @endif
                    </div>
                @endforeach
                @if (!empty($contact['support_hours']))
                    <div>
                        <p class="text-xs uppercase tracking-wide text-text-muted">Hours</p>
                        <p class="text-text-primary">{{ $contact['support_hours'] }}</p>
                    </div>
                @endif
            </div>
        </x-dashboard.card>

        <x-dashboard.card>
            <h2 class="text-base font-semibold text-text-primary mb-3">Quick actions</h2>
            <div class="flex flex-wrap gap-3">
                <x-dashboard.button :href="route('contact')" variant="secondary" size="sm">Contact Us</x-dashboard.button>
                <x-dashboard.button :href="route('help')" variant="secondary" size="sm">Help Center</x-dashboard.button>
                <x-dashboard.button :href="route($prefix.'.support.create')" size="sm" icon="plus">Open Ticket</x-dashboard.button>
            </div>
        </x-dashboard.card>

        <div>
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-base font-semibold text-text-primary">Your tickets</h2>
                <x-dashboard.button :href="route($prefix.'.support.create')" size="sm" variant="ghost" icon="plus">New ticket</x-dashboard.button>
            </div>

            <x-dashboard.table
                :empty="$tickets->isEmpty()"
                empty-title="No tickets yet"
                empty-description="Open a ticket if email and the help center do not solve your issue."
                empty-icon="support"
                :empty-action="['href' => route($prefix.'.support.create'), 'label' => 'Open Ticket']"
                striped
            >
                <x-slot:head>
                    <x-dashboard.th>Subject</x-dashboard.th>
                    <x-dashboard.th>Category</x-dashboard.th>
                    <x-dashboard.th>Status</x-dashboard.th>
                    <x-dashboard.th></x-dashboard.th>
                </x-slot:head>
                @foreach ($tickets as $t)
                    <tr class="hover:bg-muted/50">
                        <x-dashboard.td class="font-medium">{{ $t->subject }}</x-dashboard.td>
                        <x-dashboard.td>{{ $t->category }}</x-dashboard.td>
                        <x-dashboard.td>
                            <x-dashboard.badge :status="$t->status === 'open' ? 'pending' : 'completed'">{{ $t->status }}</x-dashboard.badge>
                        </x-dashboard.td>
                        <x-dashboard.td>
                            <x-dashboard.button :href="route($prefix.'.support.show', $t)" variant="link" size="xs">View</x-dashboard.button>
                        </x-dashboard.td>
                    </tr>
                @endforeach
            </x-dashboard.table>

            <div class="mt-4">
                <x-dashboard.pagination :paginator="$tickets" />
            </div>
        </div>
    </div>
</x-layout.page>
@endsection
