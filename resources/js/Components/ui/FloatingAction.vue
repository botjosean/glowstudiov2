<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Booksy's floating dark pill, bottom right.
 *
 * The same shape in the same place on every screen, but it says a different
 * thing depending on where you are: on the profile it offers Configuración, on
 * Configuración it offers help. That is the whole trick — one habit, one
 * thumb position, and the app decides what the useful next step is rather than
 * making you go find it in a menu.
 *
 * The geometry is lifted verbatim from the pill the Perfil tab already had, so
 * the two are the same object moving between screens rather than two pills
 * that merely resemble each other — including the sm: offset that keeps it
 * inside the phone-width column on a desktop browser.
 */
defineProps({
    label: { type: String, required: true },
    href: { type: String, default: null },
});

defineEmits(['click']);
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href ?? undefined"
        :type="href ? undefined : 'button'"
        class="fixed bottom-24 right-4 z-20 flex items-center gap-2 rounded-full bg-[var(--nav-bg)] py-3.5 pl-4 pr-5 text-[15px] font-semibold text-white shadow-[0_8px_20px_rgba(0,0,0,0.3)] hover:bg-black sm:right-[calc(50vw-224px)] min-[700px]:right-[calc(50vw-394px)] lg:right-[calc(50vw-496px)]"
        @click="$emit('click')"
    >
        <slot name="icon" />
        {{ label }}
    </component>
</template>
