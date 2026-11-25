@props(['name', 'class' => 'size-6'])
@php
    $paths = [
        'home' => 'M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01',
        'card' => 'M2 7a3 3 0 013-3h14a3 3 0 013 3v10a3 3 0 01-3 3H5a3 3 0 01-3-3zM2 10h20',
        'people' => 'M9 11a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.8a3.5 3.5 0 010 6.4M18 14.4c2.2.7 3.5 2.6 3.5 5.6',
        'vale' => 'M3 7a3 3 0 013-3h12v4M3 8h18v10a3 3 0 01-3 3H6a3 3 0 01-3-3z',
        'repeat' => 'M17 2l4 4-4 4M3 11V9a3 3 0 013-3h15M7 22l-4-4 4-4M21 13v2a3 3 0 01-3 3H3',
        'swap' => 'M7 7h13l-3-3M17 17H4l3 3',
        'target' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 16a4 4 0 100-8 4 4 0 000 8z',
        'chart' => 'M5 20V11M12 20V4M19 20v-6',
        'pie' => 'M21 12A9 9 0 1112 3v9z',
        'upload' => 'M12 15V4M7 9l5-5 5 5M4 20h16',
        'tag' => 'M3 12V4a1 1 0 011-1h8l9 9-9 9zM7.5 7.5h.01',
        'invite' => 'M9 11a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M19 8v6M16 11h6',
        'dots' => 'M5 12h.01M12 12h.01M19 12h.01',
        'plus' => 'M12 5v14M5 12h14',
        'out' => 'M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9',
    ];
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? '' }}"/></svg>
