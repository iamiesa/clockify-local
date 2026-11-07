<span {{ $attributes->merge(['class' => 'flex items-center gap-2 font-semibold text-ink']) }}>
    <span class="flex size-7 items-center justify-center rounded-full bg-primary text-white"><x-icon name="clock" class="size-4" /></span>
    <span class="text-lg tracking-tight">{{ config('app.name') }}</span>
</span>
