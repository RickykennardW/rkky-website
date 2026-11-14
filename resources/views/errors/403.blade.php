@extends('layouts.shell')
@section('title', 'Akses ditolak')
@section('content')
<x-empty icon="shield-check" title="Akses ditolak" description="Your account does not have permission for this action." :href="route('dashboard')" action="Kembali ke beranda" />
@endsection
