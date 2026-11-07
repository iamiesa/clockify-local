{{-- Applies the saved theme before first paint (no flash). Values: light | dark | system. --}}
<script>
    (() => {
        let mode = 'system';
        try { mode = localStorage.getItem('theme') || 'system'; } catch (e) {}
        const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
