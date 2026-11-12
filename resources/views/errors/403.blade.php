@extends('layouts.shell')
@section('title', 'Access denied')
@section('content')
<x-empty icon="shield-check" title="Access denied" description="Your account does not have permission for this action." :href="route('dashboard')" action="Back to dashboard" />
@endsection
