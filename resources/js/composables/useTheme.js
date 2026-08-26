import { ref, watchEffect } from 'vue';

const STORAGE_KEY = 'greenstock-theme';

// The inline script in app.blade.php already set the `dark` class before
// Vue booted (to avoid a flash of the wrong theme) — read that back rather
// than re-deciding the default here, so the two stay in sync.
const theme = ref(document.documentElement.classList.contains('dark') ? 'dark' : 'light');

watchEffect(() => {
    document.documentElement.classList.toggle('dark', theme.value === 'dark');
    localStorage.setItem(STORAGE_KEY, theme.value);
});

export function useTheme() {
    function toggleTheme() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    return { theme, toggleTheme };
}
