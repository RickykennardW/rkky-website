@extends('layouts.shell')
@section('title', 'Halaman tidak ditemukan')
@section('content')
<x-empty icon="search" title="Halaman tidak ditemukan" description="Halaman ini mungkin sudah dipindahkan atau tidak tersedia." :href="route('dashboard')" action="Kembali ke beranda" />
@endsection
