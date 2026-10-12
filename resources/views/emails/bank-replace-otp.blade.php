@extends('emails.layouts.transactional')

@section('title', 'Verify payout account change')
@section('heading', 'Verify payout account change')

@section('content')
    <p style="margin:0 0 16px;">Someone requested to change the payout accounts you withdraw to.</p>
    <p style="margin:0 0 8px;">Your verification code is:</p>
    <p style="margin:0 0 16px;font-size:28px;font-weight:700;letter-spacing:0.2em;color:#004AC6;">{{ $code }}</p>
    <p style="margin:0;color:#6b7280;font-size:14px;">This code expires in 10 minutes. If this was not you, contact support immediately.</p>
@endsection
