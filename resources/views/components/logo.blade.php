@props(['size' => 'w-10 h-10', 'alt' => 'Tolery AI CAD'])

<img src="{{ Vite::asset('resources/images/chat-icon.png') }}"
     alt="{{ $alt }}"
     {{ $attributes->merge(['class' => $size]) }} />
