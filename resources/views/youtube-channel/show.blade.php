@extends($layout)

@section('title', 'YouTube channel')

@section('content')
<x-layout.page title="YouTube channel" subtitle="See and manage the YouTube channel connected to your account." width="full">
    @include('account.partials.navigation')

    <div id="dashboard-tab-panel" class="mt-4">
        @include('youtube-channel.panel')
    </div>
</x-layout.page>
@endsection
