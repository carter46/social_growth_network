@extends('emails.layouts.transactional')

@section('title', 'Payout accounts updated')
@section('heading', 'Payout accounts updated')

@section('content')
    <p style="margin:0 0 16px;">{{ $summary }}</p>
    @if ($account)
        <p style="margin:0 0 8px;"><strong>Account:</strong> {{ $account->summary() }}</p>
        <p style="margin:0 0 8px;"><strong>Holder name:</strong> {{ $account->account_holder_name }}</p>
    @endif
    <p style="margin:0 0 16px;"><strong>Time:</strong> {{ now()->toDateTimeString() }} UTC</p>
    <p style="margin:0;color:#6b7280;font-size:14px;">If this was not you, contact support immediately.</p>
@endsection
