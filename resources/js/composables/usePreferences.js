import { ref, watch } from 'vue';
import { setLocale, i18n } from '../i18n';

const TIME_FORMAT_KEY = 'glowstudio.timeFormat';
const WHATSAPP_PROMPT_KEY = 'glowstudio.whatsappPrompt';

function initialTimeFormat() {
    const stored = localStorage.getItem(TIME_FORMAT_KEY);
    return stored === '24' ? '24' : '12';
}

function initialWhatsappPrompt() {
    return localStorage.getItem(WHATSAPP_PROMPT_KEY) === 'never' ? 'never' : 'ask';
}

const timeFormat = ref(initialTimeFormat());
const whatsappPrompt = ref(initialWhatsappPrompt());

watch(timeFormat, (value) => {
    localStorage.setItem(TIME_FORMAT_KEY, value);
});

watch(whatsappPrompt, (value) => {
    localStorage.setItem(WHATSAPP_PROMPT_KEY, value);
});

export function usePreferences() {
    const locale = i18n.global.locale;

    function setLanguage(value) {
        setLocale(value);
    }

    function setTimeFormat(value) {
        timeFormat.value = value;
    }

    function setWhatsappPrompt(value) {
        whatsappPrompt.value = value;
    }

    return { locale, setLanguage, timeFormat, setTimeFormat, whatsappPrompt, setWhatsappPrompt };
}
