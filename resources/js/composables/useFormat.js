import { usePreferences } from './usePreferences';

export function useFormat() {
    const { timeFormat, locale } = usePreferences();

    function formatPrice(amount) {
        return `$${Number(amount).toFixed(0)}`;
    }

    function formatDuration(minutes) {
        if (minutes < 60) return `${minutes} min`;
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;
        return rest === 0 ? `${hours}h` : `${hours}h ${rest} min`;
    }

    function formatTime(hour24, minute = 0) {
        if (timeFormat.value === '24') {
            return `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        }
        const period = hour24 >= 12 ? 'PM' : 'AM';
        const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
        return `${hour12}:${String(minute).padStart(2, '0')} ${period}`;
    }

    // The backend always sends raw UTC timestamps (dates stay UTC in the
    // database); these format them for display using the viewer's language
    // and time-format preferences, which only exist client-side.
    function formatDayLabel(date) {
        return date.toLocaleDateString(locale.value === 'es' ? 'es-ES' : 'en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        });
    }

    function formatDateTimeLabel(date) {
        return `${formatDayLabel(date)} · ${formatTime(date.getHours(), date.getMinutes())}`;
    }

    return { formatPrice, formatDuration, formatTime, formatDayLabel, formatDateTimeLabel };
}
