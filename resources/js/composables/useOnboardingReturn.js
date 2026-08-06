import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Steps opened from the Inicio checklist carry `?desde=inicio`. Pages use
 * this to send the user back to the checklist after a successful save, so
 * the flow reads as a wizard: complete a step, see your progress advance.
 */
export function useOnboardingReturn() {
    const page = usePage();

    const fromInicio = computed(() => page.url.includes('desde=inicio'));

    function returnToInicio() {
        if (fromInicio.value) {
            router.visit('/admin/inicio');
        }
    }

    return { fromInicio, returnToInicio };
}
