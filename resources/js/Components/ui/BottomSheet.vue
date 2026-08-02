<script setup>
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    closeOnBackdrop: { type: Boolean, default: true },
});

const open = defineModel({ type: Boolean, default: false });

function close() {
    open.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

watch(
    open,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';
        if (isOpen) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center bg-[var(--backdrop)]/70"
                @click.self="closeOnBackdrop && close()"
            >
                <Transition
                    enter-active-class="transition-transform duration-250 ease-out"
                    enter-from-class="translate-y-full"
                    leave-active-class="transition-transform duration-200 ease-in"
                    leave-to-class="translate-y-full"
                    appear
                >
                    <div
                        class="max-h-[92vh] w-full max-w-[480px] overflow-y-auto rounded-t-3xl bg-[var(--surface)] p-6 shadow-[0_-10px_40px_rgba(15,23,42,0.2)]"
                    >
                        <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-[var(--border-strong)]" />
                        <slot />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
