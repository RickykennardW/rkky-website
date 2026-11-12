@extends('layouts.base')
@section('title', $registering ? 'Create an account' : 'Sign in')

@section('body')
<main class="grid min-h-dvh lg:grid-cols-[1fr_0.9fr]">
    <div class="mx-auto flex w-full max-w-xl flex-col gap-9 px-5 py-7 sm:px-12 sm:py-10">
        <div class="flex flex-wrap items-center justify-between gap-4"><x-brand /><a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-sm text-muted hover:text-ink"><x-icon name="arrow-left" class="size-4" /> Dashboard</a></div>
        <section class="panel my-auto grid gap-5 p-5 sm:p-8">
            <div><h1 class="page-title">{{ $registering ? 'Create your account' : 'Sign in' }}</h1>
                <p class="muted mt-2">{{ $registering ? 'Use your campus email to book rooms and borrow equipment.' : 'Access your campus bookings and equipment loans.' }}</p></div>
            <x-feedback />
            <form method="post" action="{{ route($registering ? 'register' : 'login') }}" class="grid gap-4">
                @csrf
                @if($registering)<label class="label">Full name<input class="input" name="full_name" value="{{ old('full_name') }}" autocomplete="name" required maxlength="100"></label>@endif
                <label class="label">Email<input class="input" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="254" placeholder="you@campus.edu"></label>
                <label class="label">Password<input class="input" name="password" type="password" autocomplete="{{ $registering ? 'new-password' : 'current-password' }}" required @if($registering) minlength="8" maxlength="72" @endif></label>
                @if($registering)
                    <label class="label">Confirm password<input class="input" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="72"></label>
                @else
                    <label class="flex items-center gap-2 text-sm text-muted"><input class="checkbox" type="checkbox" name="remember" value="1"> Keep me signed in</label>
                @endif
                <button type="submit" class="button button-primary">{{ $registering ? 'Create account' : 'Sign in' }} <x-icon name="arrow-right" class="size-4" /></button>
            </form>
            <p class="muted">{{ $registering ? 'Already registered?' : 'New to CampusSpace?' }}
                <a class="font-semibold text-accent hover:underline" href="{{ route($registering ? 'login' : 'register') }}">{{ $registering ? 'Sign in' : 'Create an account' }}</a></p>
        </section>
    </div>
    <div class="relative hidden overflow-hidden border-l border-line lg:block">
        <img src="{{ asset('images/rooms/seminar.png') }}" alt="Illustration of a campus seminar room" class="absolute inset-0 size-full object-cover">
        <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-[#0b0f14] via-[#0b0f14]/15 to-transparent"></div>
    </div>
</main>
@endsection
