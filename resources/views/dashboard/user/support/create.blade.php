@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'New Support Ticket')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="New Support Ticket"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Support', route($prefix.'.support.index')],
        ['New ticket', null],
    ]"
>
    <x-dashboard.card>
        <form method="POST" action="{{ route($prefix.'.support.store') }}" enctype="multipart/form-data" class="w-full space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <x-dashboard.select label="Category" name="category">
                @foreach ($categories as $c)
                    <option value="{{ $c }}">{{ ucfirst(str_replace('_', ' ', $c)) }}</option>
                @endforeach
            </x-dashboard.select>
            <x-dashboard.input label="Subject" name="subject" required />
            <x-dashboard.textarea label="Message" name="body" :rows="5" required />
            <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Evidence (optional)</label>
                <input
                    type="file"
                    name="attachments[]"
                    multiple
                    accept="image/*,.pdf,.doc,.docx,.txt"
                    class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-2"
                >
                <p class="mt-1 text-xs text-text-muted">Images, PDF, or documents · max 3 files · 8 MB each · auto-deleted after 72 hours</p>
                @error('attachments')
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
            <x-dashboard.button type="submit" icon="support" x-bind:disabled="submitting">Submit</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
