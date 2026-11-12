@extends('layouts.base')
@section('title', 'Verify your email')
@section('body')
<main class="mx-auto grid min-h-dvh max-w-xl content-start gap-6 px-5 py-10">
    <x-brand />
    <section class="panel grid gap-5 p-6 sm:p-8">
        <x-heading title="Verify your email" description="Open the link sent to your email to access the campus workspace." />
        <x-feedback />
        <div class="flex flex-wrap gap-3">
            <form method="post" action="{{ route('verification.send') }}">@csrf<button class="button button-primary">Resend verification</button></form>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-ghost">Sign out</button></form>
        </div>
    </section>
</main>
@endsection
