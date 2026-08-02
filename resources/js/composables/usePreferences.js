import { ref, watch } from 'vue';
import { setLocale, i18n } from '../i18n';

const TIME_FORMAT_KEY = 'glowstudio.timeFormat';

function initialTimeFormat() {
    const stored = localStorage.getItem(TIME_FORMAT_KEY);
    return stored === '24' ? '24' : '12';
}

const timeFormat = ref(initialTimeFormat());

watch(timeFormat, (value) => {
    localStorage.setItem(TIME_FORMAT_KEY, value);
});

export function usePreferences() {
    const locale = i18n.global.locale;

    function setLanguage(value) {
        setLocale(value);
    }

    function setTimeFormat(value) {
        timeFormat.value = value;
    }

    return { locale, setLanguage, timeFormat, setTimeFormat };
}
