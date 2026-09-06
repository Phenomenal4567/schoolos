<script>
    (function () {
        var stored = localStorage.getItem('schoolos-theme') || 'system';
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = stored === 'system' ? (prefersDark ? 'dark' : 'light') : stored;
        document.documentElement.dataset.theme = theme;
        document.documentElement.dataset.themeChoice = stored;
        document.documentElement.style.colorScheme = theme;
    })();
</script>
