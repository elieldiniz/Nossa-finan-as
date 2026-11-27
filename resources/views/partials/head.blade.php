<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? 'Nossa Finanças' }}</title>
@php $themeKey = \App\Support\Theme::key(auth()->user()); @endphp
<meta name="theme-color" content="{{ \App\Support\Theme::palette($themeKey)['brand'] }}">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
<style id="nf-theme">{!! \App\Support\Theme::css($themeKey) !!}</style>
