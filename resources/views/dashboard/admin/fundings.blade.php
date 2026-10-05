@extends('layouts.dashboard-admin')

@section('title', 'Deposits')

@section('content')
<x-layout.page
    title="Wallet fundings"
    subtitle="Online checkout, reserved accounts, and historical records."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Wallet fundings', null],
    ]"
>
    <x-dashboard.table :empty="$fundings->isEmpty()" empty-title="No deposit requests" empty-description="Online payment and reserved-account credits appear here. Order bank transfers are under Orders." empty-icon="deposit" striped>
        <x-slot:head>
            <x-dashboard.th>User / Ref</x-dashboard.th>
            <x-dashboard.th>Method</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>

        @foreach ($fundings as $f)
            <tr>
                <x-dashboard.td class="min-w-[10rem] max-w-[16rem]">
                    <div class="font-medium text-text-primary">{{ \App\Models\User::labelFor($f->user) }}</div>
                    <div class="mt-0.5 font-mono text-[11px] leading-snug text-text-muted break-all">{{ $f->reference }}</div>
                </x-dashboard.td>
                <x-dashboard.td>{{ $f->method }}</x-dashboard.td>
                <x-dashboard.td>₦{{ number_format($f->amount, 2) }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$f->status" /></x-dashboard.td>
                <x-dashboard.td>
                    @php
                        $hasProof = ! empty($f->metadata['proof_path'] ?? null);
                        $actionCount = (int) $hasProof + (int) ($f->status === 'pending') * 2 + (int) ($f->status === 'approved');
                    @endphp
                    @if ($actionCount === 1 && $hasProof)
                        <x-dashboard.button :href="route('admin.fundings.proof', $f)" variant="link" size="xs" target="_blank">View proof</x-dashboard.button>
                    @elseif ($actionCount >= 1)
                        <x-dashboard.row-actions>
                            @if ($hasProof)
                                <x-dashboard.menu-item :href="route('admin.fundings.proof', $f)" target="_blank">View proof</x-dashboard.menu-item>
                            @endif
                            @if ($f->status === 'pending')
                                <x-dashboard.menu-item type="button" variant="success" @click="$dispatch('open-modal', 'approve-funding-{{ $f->id }}')">Approve</x-dashboard.menu-item>
                                <x-dashboard.menu-item type="button" variant="danger" @click="$dispatch('open-modal', 'reject-funding-{{ $f->id }}')">Reject</x-dashboard.menu-item>
                            @elseif ($f->status === 'approved')
                                <x-dashboard.menu-item type="button" @click="$dispatch('open-modal', 'reverse-funding-{{ $f->id }}')">Reverse</x-dashboard.menu-item>
                            @endif
                        </x-dashboard.row-actions>
                    @endif

                    @if ($f->status === 'pending')
                        <x-dashboard.modal
                            name="approve-funding-{{ $f->id }}"
                            title="Approve deposit?"
                            confirm-label="Approve"
                            :form-action="route('admin.fundings.approve', $f)"
                        >
                            Credit ₦{{ number_format($f->amount, 2) }} to {{ \App\Models\User::labelFor($f->user) }}?
                        </x-dashboard.modal>
                        <x-dashboard.modal
                            name="reject-funding-{{ $f->id }}"
                            title="Reject deposit?"
                            variant="danger"
                            confirm-label="Reject"
                            :form-action="route('admin.fundings.reject', $f)"
                        >
                            This will mark the funding as rejected. No wallet credit will be issued.
                        </x-dashboard.modal>
                    @elseif ($f->status === 'approved')
                        <x-dashboard.modal
                            name="reverse-funding-{{ $f->id }}"
                            title="Reverse deposit?"
                            variant="warning"
                            confirm-label="Reverse"
                            :form-action="route('admin.fundings.reverse', $f)"
                        >
                            <x-slot:form>
                                <input type="hidden" name="reason" value="Admin reversal">
                            </x-slot:form>
                            This will debit the user wallet for ₦{{ number_format($f->amount, 2) }}.
                        </x-dashboard.modal>
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$fundings" />
    </x-slot:pagination>
</x-layout.page>
@endsection
