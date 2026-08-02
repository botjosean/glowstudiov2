import { createI18n } from 'vue-i18n';
import es from './es.json';
import en from './en.json';

const STORAGE_KEY = 'glowstudio.locale';

function initialLocale() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'es' || stored === 'en') return stored;
    return 'es';
}

export const i18n = createI18n({
    legacy: false,
    locale: initialLocale(),
    fallbackLocale: 'es',
    messages: { es, en },
});

export function setLocale(locale) {
    i18n.global.locale.value = locale;
    localStorage.setItem(STORAGE_KEY, locale);
    document.documentElement.lang = locale;
}
