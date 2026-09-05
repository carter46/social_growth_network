@extends('emails.layouts.transactional')

@section('title', 'Reset your password')
@section('heading', 'Reset your password')

@section('content')
    <p style="margin:0 0 16px;">We received a request to reset your password.</p>
    <p style="margin:0 0 16px;">
        <a href="{{ $url }}" style="display:inline-block;background:#004AC6;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;">
            Reset password
        </a>
    </p>
    <p style="margin:0;color:#6b7280;font-size:14px;">This link expires in {{ $count }} minutes. If you did not request a reset, you can ignore this email.</p>
@endsection
