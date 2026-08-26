<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), ['fa', 'ps', 'pa']) ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/appforest/logo2.jpg') }}">
    @vite('resources/js/app.js')


    @inertiaHead
    @routes
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
