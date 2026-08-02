import { usePreferences } from './usePreferences';

export function useFormat() {
    const { timeFormat } = usePreferences();

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

    return { formatPrice, formatDuration, formatTime };
}
