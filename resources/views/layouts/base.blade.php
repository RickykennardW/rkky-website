<!doctype html>
<html lang="en" class="dark" data-app="campusspace">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Book campus rooms, borrow equipment, and plan event setups.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('campusspace.svg') }}">
    <title>@yield('title', 'Dashboard') · CampusSpace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @yield('body')
</body>
</html>
