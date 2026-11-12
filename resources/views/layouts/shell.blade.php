@extends('layouts.base')

@section('body')
@php($account = auth()->user())
<div class="min-h-dvh" x-data>
    <a href="#main" class="fixed top-3 left-3 z-50 -translate-y-[150%] rounded bg-brand px-4 py-2 text-canvas focus:translate-y-0">Skip to content</a>
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-[226px] border-r border-line bg-[#10161e] lg:block">
        <x-sidebar :account="$account" />
    </aside>
    <dialog x-ref="navigation" aria-label="Navigation" class="fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-[min(90vw,320px)] max-w-none border-r border-line bg-[#10161e] text-ink backdrop:bg-black/70">
        <x-sidebar :account="$account" mobile />
    </dialog>
    <div class="lg:pl-[226px]">
        <header class="sticky top-0 z-20 border-b border-line bg-canvas">
            <div class="mx-auto flex min-h-[68px] max-w-[1470px] items-center justify-between gap-3 px-4 sm:px-7 xl:px-9">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" x-on:click="$refs.navigation.showModal()" class="button button-ghost px-2 lg:hidden" aria-label="Open navigation"><x-icon name="menu" /></button>
                    <span class="hidden text-sm text-muted sm:inline">CampusSpace</span>
                    <span aria-hidden="true" class="hidden text-[#526173] sm:inline">/</span>
                    <span class="truncate text-sm font-medium">@yield('title', 'Dashboard')</span>
                </div>
                <div class="flex items-center gap-2">
                    @auth
                        <a class="button button-ghost px-2.5" href="{{ route('notifications.index') }}" aria-label="Notifications"><x-icon name="bell" /></a>
                        <a class="button button-primary" href="{{ route('bookings.create') }}"><x-icon name="calendar-plus" class="size-4" /><span>New booking</span></a>
                    @else
                        <a class="button button-primary" href="{{ route('login') }}">Sign in <x-icon name="arrow-up-right" class="size-4" /></a>
                    @endauth
                </div>
            </div>
        </header>
        <main id="main" class="mx-auto grid max-w-[1470px] gap-7 px-4 py-6 sm:px-7 sm:py-8 xl:px-9">
            <x-feedback />
            @yield('content')
        </main>
    </div>
</div>
@endsection
