@extends('layouts.shell')
@section('title', 'Page not found')
@section('content')
<x-empty icon="search" title="Page not found" description="This resource may have moved or is unavailable to your account." :href="route('dashboard')" action="Back to dashboard" />
@endsection
