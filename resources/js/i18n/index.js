import { createI18n } from 'vue-i18n';
import es from './es.json';
import en from './en.json';

const STORAGE_KEY = 'glowstudio.locale';
const COOKIE_KEY = 'locale';

function initialLocale() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'es' || stored === 'en') return stored;
    return 'es';
}

// The server has no other way to know the UI language (it's client-only
// state) — this cookie is what SetLocaleFromCookie reads so validation
// errors and other server-rendered strings match the UI instead of always
// falling back to APP_LOCALE.
function writeLocaleCookie(locale) {
    document.cookie = `${COOKIE_KEY}=${locale};path=/;max-age=31536000;samesite=lax`;
}

const initial = initialLocale();
writeLocaleCookie(initial);

export const i18n = createI18n({
    legacy: false,
    locale: initial,
    fallbackLocale: 'es',
    messages: { es, en },
});

export function setLocale(locale) {
    i18n.global.locale.value = locale;
    localStorage.setItem(STORAGE_KEY, locale);
    document.documentElement.lang = locale;
    writeLocaleCookie(locale);
}
