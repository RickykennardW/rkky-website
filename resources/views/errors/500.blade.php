@extends('layouts.base')
@section('title', 'CampusSpace is unavailable')
@section('body')
<main class="mx-auto grid min-h-dvh max-w-xl content-start gap-6 px-5 py-10">
    <x-brand />
    <x-empty icon="circle-alert" title="CampusSpace is temporarily unavailable" description="Try again in a moment or contact campus support." :href="url('/')" action="Back to dashboard" />
</main>
@endsection
