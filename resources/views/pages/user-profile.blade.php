@extends('layouts.marketing')

@section('title', $username)

@section('content')
<section class="py-8">
    <div class="max-w-marketing mx-auto px-5 sm:px-6 space-y-8">
        <section class="glassmorphism rounded-xl border border-white/10 overflow-hidden">
            <div class="h-32 bg-gradient-to-r from-primary/20 via-primary/40 to-primary/20"></div>
            <div class="px-8 pb-8 flex flex-col md:flex-row gap-6 items-start -mt-12">
                <div class="relative">
                    <div class="size-24 md:size-32 rounded-2xl bg-slate-700 border-4 border-slate-900 flex items-center justify-center">
                        <x-ui.icon name="user" class="w-12 h-12 text-slate-500" />
                    </div>
                    <div class="absolute bottom-2 right-2 bg-primary text-white rounded-full p-1 shadow-lg flex items-center justify-center">
                        <x-ui.icon name="verified" class="w-4 h-4" />
                    </div>
                </div>
                <div class="flex-1 pt-14 md:pt-8">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white">{{ $username }}</h1>
                                <span class="bg-primary/10 text-primary text-xs font-bold px-2.5 py-1 rounded-full border border-primary/20">TRADER</span>
                            </div>
                            <p class="text-slate-400 mt-1 flex items-center gap-1">
                                <x-ui.icon name="user" class="w-4 h-4" />
                                Public profile
                            </p>
                        </div>
                        @auth
                            <div class="flex gap-3">
                                <a href="{{ route('dashboard.services') }}" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-hover transition-all shadow-md">Services</a>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </section>
        <div class="glassmorphism rounded-xl p-8 border border-white/10">
            <p class="text-slate-400">Profile and listings for <strong class="text-white">{{ $username }}</strong> will appear here. This page is static until user profiles are fully connected to the database.</p>
        </div>
    </div>
</section>
@endsection
