@extends('layouts.shell')
@section('title', 'Masuk Demo')

@section('content')
<x-heading title="Masuk Demo" description="Pilih peran untuk mencoba CampusSpace. Peminjaman demo disimpan di sesi browser ini." />
<div class="grid max-w-4xl gap-4 sm:grid-cols-2">
    <form class="panel grid gap-3 p-5 sm:p-6" method="post" action="{{ route('login') }}">
        @csrf
        <input type="hidden" name="role" value="student">
        <h2 class="section-title">Mahasiswa</h2>
        <p class="muted">Cari ruangan dan ajukan peminjaman.</p>
        <button class="button button-primary mt-2 justify-self-start">Masuk sebagai mahasiswa <x-icon name="arrow-right" class="size-4" /></button>
    </form>
    <form class="panel grid gap-3 p-5 sm:p-6" method="post" action="{{ route('login') }}">
        @csrf
        <input type="hidden" name="role" value="admin">
        <h2 class="section-title">Admin</h2>
        <p class="muted">Tinjau pengajuan dan pantau ruangan.</p>
        <button class="button button-secondary mt-2 justify-self-start">Masuk sebagai admin <x-icon name="arrow-right" class="size-4" /></button>
    </form>
</div>
@endsection
