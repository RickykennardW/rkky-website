@extends('layouts.shell')
@section('title', 'Choose demo role')

@section('content')
<x-heading title="Choose demo role" description="Explore CampusSpace with fictional campus data. Requests stay in this browser session." />
<div class="grid gap-4 sm:grid-cols-2">
    <form class="panel grid gap-4 p-5" method="post" action="{{ route('login') }}">
        @csrf
        <input type="hidden" name="role" value="student">
        <h2 class="section-title">Student</h2>
        <p class="muted">Browse rooms, plan an event, and request a reservation.</p>
        <button class="button button-primary justify-self-start">Enter student demo</button>
    </form>
    <form class="panel grid gap-4 p-5" method="post" action="{{ route('login') }}">
        @csrf
        <input type="hidden" name="role" value="admin">
        <h2 class="section-title">Administrator</h2>
        <p class="muted">Review simulated bookings created in this browser.</p>
        <button class="button button-secondary justify-self-start">Enter admin demo</button>
    </form>
</div>
@endsection
