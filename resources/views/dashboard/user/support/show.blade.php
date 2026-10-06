@extends($layout ?? 'layouts.dashboard-user')

@section('title', $ticket->subject)

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="{{ $ticket->subject }}"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Support', route($prefix.'.support.index')],
        ['Ticket', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.badge :status="$ticket->status" />
    </x-slot:actions>

    @include('dashboard.user.support._thread', [
        'viewer' => 'member',
        'replyAction' => route($prefix.'.support.reply', $ticket),
        'submitLabel' => __('Reply'),
    ])
</x-layout.page>
@endsection
