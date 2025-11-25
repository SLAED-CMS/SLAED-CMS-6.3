// Apply theme immediately to prevent flash of white
(function() {
    const savedTheme = localStorage.getItem('theme');
    const osPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const initialTheme = savedTheme === 'dark' || (!savedTheme && osPrefersDark) ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', initialTheme);
})();