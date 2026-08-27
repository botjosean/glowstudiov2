<script setup>
/**
 * Iniciar sesión / Crear cuenta como una sola pantalla con dos pestañas, en
 * vez de dos páginas sin relación visible entre sí. Sigue siendo dos rutas
 * reales (/iniciar-sesion, /crear-cuenta) — cada una con su propio formulario
 * y su propia validación intactos — esto solo navega entre ellas con una
 * pastilla arriba en vez de un enlace de texto abajo.
 */
import { router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, required: true }, // 'signin' | 'signup'
});

function go(tab) {
    if (tab === props.active) return;
    router.visit(tab === 'signin' ? '/iniciar-sesion' : '/crear-cuenta', { preserveScroll: true });
}
</script>

<template>
    <div class="mb-6 flex gap-1 rounded-2xl bg-[var(--surface-mute)] p-1">
        <button
            type="button"
            class="flex-1 rounded-xl py-2.5 text-[14px] font-bold transition-colors"
            :class="active === 'signin'
                ? 'bg-[var(--surface)] text-[var(--text-strong)] shadow-[0_1px_3px_rgba(15,23,42,0.12)]'
                : 'text-[var(--text-faint)] hover:text-[var(--text-mute)]'"
            @click="go('signin')"
        >
            {{ $t('menu.signIn') }}
        </button>
        <button
            type="button"
            class="flex-1 rounded-xl py-2.5 text-[14px] font-bold transition-colors"
            :class="active === 'signup'
                ? 'bg-[var(--surface)] text-[var(--text-strong)] shadow-[0_1px_3px_rgba(15,23,42,0.12)]'
                : 'text-[var(--text-faint)] hover:text-[var(--text-mute)]'"
            @click="go('signup')"
        >
            {{ $t('menu.createAccount') }}
        </button>
    </div>
</template>
