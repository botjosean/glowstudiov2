import { ref, watchEffect } from 'vue';

const STORAGE_KEY = 'glowstudio.theme';

function initialTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') return stored;
    return 'light';
}

const theme = ref(initialTheme());

watchEffect(() => {
    document.documentElement.setAttribute('data-theme', theme.value);
    localStorage.setItem(STORAGE_KEY, theme.value);
});

export function useTheme() {
    function setTheme(value) {
        theme.value = value;
    }

    function toggleTheme() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    return { theme, setTheme, toggleTheme };
}
