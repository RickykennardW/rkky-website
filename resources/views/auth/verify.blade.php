@extends('layouts.base')
@section('title', 'Verifikasi Email')
@section('body')
<main class="mx-auto grid min-h-dvh max-w-xl content-start gap-6 px-5 py-10">
    <x-brand />
    <section class="panel grid gap-5 p-6 sm:p-8">
        <x-heading title="Verifikasi Email" description="Buka tautan yang dikirim ke email kampusmu." />
        <x-feedback />
        <div class="flex flex-wrap gap-3">
            <form method="post" action="{{ route('verification.send') }}">@csrf<button class="button button-primary">Kirim ulang tautan</button></form>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-ghost">Keluar</button></form>
        </div>
    </section>
</main>
@endsection
