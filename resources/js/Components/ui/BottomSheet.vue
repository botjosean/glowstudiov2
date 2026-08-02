<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { acquireBodyLock, releaseBodyLock } from '../../composables/useBodyScrollLock';

defineProps({
    closeOnBackdrop: { type: Boolean, default: true },
});

const open = defineModel({ type: Boolean, default: false });

function close() {
    open.value = false;
}

// The lock is a shared, reference-counted resource (see the composable) —
// this instance must only release what it itself acquired.
let holdsLock = false;

watch(
    open,
    (isOpen) => {
        if (isOpen && !holdsLock) {
            acquireBodyLock(close);
            holdsLock = true;
        } else if (!isOpen && holdsLock) {
            releaseBodyLock(close);
            holdsLock = false;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (holdsLock) {
        releaseBodyLock(close);
        holdsLock = false;
    }
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
                        role="dialog"
                        aria-modal="true"
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
