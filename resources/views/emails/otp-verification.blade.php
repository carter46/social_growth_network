@extends('emails.layouts.transactional')

@section('title', 'Verify your email')
@section('heading', 'Verify your email')

@section('content')
    <p style="margin:0 0 16px;">Use this code to verify your email address:</p>
    <p style="margin:0 0 16px;font-size:28px;font-weight:700;letter-spacing:0.25em;color:#004AC6;">{{ $code }}</p>
    <p style="margin:0;color:#6b7280;font-size:14px;">This code expires in 15 minutes. If you did not request this, you can ignore this email.</p>
@endsection
