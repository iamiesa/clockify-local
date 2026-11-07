@props(['user', 'size' => 'size-8 text-xs'])
<span {{ $attributes->merge(['class' => "$size inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white"]) }} style="background-color: {{ $user->avatar_color }}" title="{{ $user->name }}">{{ $user->initials() }}</span>
