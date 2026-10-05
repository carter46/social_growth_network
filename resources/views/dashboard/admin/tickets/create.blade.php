@extends('layouts.dashboard-admin')

@section('title', 'Open Support Ticket')

@section('content')
<x-layout.page
    title="Open ticket on behalf of user"
    subtitle="Create a support ticket assigned to you."
    width="md"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Support Tickets', route('admin.tickets')],
        ['Open ticket', null],
    ]"
>
    <x-dashboard.card variant="solid">
        <form
            method="POST"
            action="{{ route('admin.tickets.store') }}"
            class="space-y-4"
            x-data="{
                usersByType: @js($usersByType),
                categoriesByType: @js($categoriesByType),
                userType: @js(old('user_type', $prefillType)),
                userId: @js((string) old('user_id', $prefillUserId ?? '')),
                category: @js(old('category', '')),
                onTypeChange() {
                    this.userId = '';
                    this.category = Object.keys(this.categoriesByType[this.userType] || {})[0] || '';
                },
                init() {
                    if (! this.category || ! (this.category in (this.categoriesByType[this.userType] || {}))) {
                        this.category = Object.keys(this.categoriesByType[this.userType] || {})[0] || '';
                    }
                },
            }"
        >
            @csrf
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">User type</label>
                <select name="user_type" x-model="userType" @change="onTypeChange()" class="w-full rounded-xl border-border-default bg-elevated" required>
                    <option value="creator">Creator</option>
                    <option value="agent">Agent</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">User</label>
                <select name="user_id" x-model="userId" class="w-full rounded-xl border-border-default bg-elevated" required>
                    <option value="" x-text="userType === 'agent' ? 'Select agent...' : 'Select creator...'"></option>
                    <template x-for="u in (usersByType[userType] || [])" :key="u.id">
                        <option :value="String(u.id)" x-text="u.label" :selected="String(u.id) === userId"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-text-muted" x-show="(usersByType[userType] || []).length === 0" x-cloak>No accounts of this type yet.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Category</label>
                <select name="category" x-model="category" class="w-full rounded-xl border-border-default bg-elevated" required>
                    <template x-for="(label, value) in (categoriesByType[userType] || {})" :key="value">
                        <option :value="value" x-text="label" :selected="value === category"></option>
                    </template>
                </select>
            </div>
            <x-dashboard.input name="subject" label="Subject" :value="old('subject')" required />
            <x-dashboard.textarea name="body" label="Message" :rows="5" required>{{ old('body') }}</x-dashboard.textarea>
            <x-dashboard.select name="priority" label="Priority">
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </x-dashboard.select>
            <x-dashboard.button type="submit" variant="primary">Create ticket</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
