@php
    // Ajuste ici si ton header fait autre chose que 96px
    $HEADER_H = 120; // en px
@endphp

<div class="mx-auto w-full max-w-[1600px] px-4 lg:px-6">

    {{-- Grille pleine hauteur de fenêtre (moins le header) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 overflow-y-auto"
         style="height: calc(100vh - {{ $HEADER_H }}px);">

        {{-- GAUCHE (1/3) — Chat sticky full height --}}
