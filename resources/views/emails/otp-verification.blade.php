@extends('emails.layouts.transactional')

@section('title', 'Verify your email')
@section('heading', 'Verify your email')
@section('preheader', 'Your verification code is '.$code.'. It expires in 15 minutes.')

@section('content')
    <p style="margin:0 0 16px;">Thanks for signing up. Enter this code on the verification page to confirm your email address:</p>
    <p style="margin:0 0 16px;font-size:28px;font-weight:700;letter-spacing:0.25em;color:#004AC6;">{{ $code }}</p>
    <p style="margin:0 0 12px;color:#6b7280;font-size:14px;">This code expires in 15 minutes. Never share it with anyone.</p>
    <p style="margin:0;color:#6b7280;font-size:14px;">You received this email because this address was used to create an account. If that wasn't you, you can safely ignore it.</p>
@endsection
