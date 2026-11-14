@extends('layouts.base')

@section('body')
@php($account = auth()->user())
<div class="min-h-dvh lg:grid lg:grid-cols-[14rem_minmax(0,1fr)]" x-data>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-brand focus:p-3 focus:text-canvas">Ke konten utama</a>
    <aside class="sticky top-0 hidden h-dvh border-r border-line bg-panel lg:block"><x-sidebar :account="$account" /></aside>
    <dialog x-ref="navigation" aria-label="Menu utama" class="fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-[min(88vw,320px)] max-w-none border-r border-line bg-panel text-ink backdrop:bg-black/65">
        <x-sidebar :account="$account" mobile />
    </dialog>
    <div class="min-w-0">
        <header class="border-b border-line bg-panel/50">
            <div class="mx-auto flex min-h-17 max-w-7xl flex-wrap items-center justify-between gap-2 px-4 sm:px-7 xl:px-10">
                <div class="flex min-w-0 items-center gap-2.5">
                    <button type="button" x-on:click="$refs.navigation.showModal()" class="button button-ghost px-2.5 lg:hidden" aria-label="Buka menu"><x-icon name="menu" class="size-5" /></button>
                    <span class="hidden truncate text-sm font-medium text-muted lg:block">@yield('title', 'Beranda')</span>
                    <div class="lg:hidden"><x-brand /></div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if(config('campusspace.demo'))
                        <span class="hidden text-xs text-muted sm:inline">Mode demo</span>
                        @auth<a class="button button-ghost" href="{{ route('login') }}">Ganti peran</a>@endauth
                    @endif
                    @auth
                        @unless(config('campusspace.demo'))
                            <a class="button button-ghost px-2.5" href="{{ route('notifications.index') }}" aria-label="Notifikasi"><x-icon name="bell" /></a>
                        @endunless
                    @else
                        @unless(request()->routeIs('dashboard', 'login'))
                            <a class="button button-ghost" href="{{ route('login') }}">Masuk</a>
                        @endunless
                    @endauth
                </div>
            </div>
        </header>
        <main id="main" class="mx-auto grid w-full max-w-7xl min-w-0 gap-7 px-4 py-6 sm:px-7 sm:py-8 xl:px-10">
            <x-feedback />
            @yield('content')
        </main>
    </div>
</div>
@endsection
