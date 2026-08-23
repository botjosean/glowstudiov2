<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { acquireBodyLock, releaseBodyLock } from '../../composables/useBodyScrollLock';

defineProps({
    closeOnBackdrop: { type: Boolean, default: true },
});

const open = defineModel({ type: Boolean, default: false });

function close() {
    open.value = false;
}

// On a phone the on-screen keyboard shrinks the VISUAL viewport but leaves the
// LAYOUT viewport untouched, so a `fixed inset-0` overlay keeps its full height
// and this sheet's action buttons end up stranded behind the keyboard — with
// no amount of scrolling able to reach them, because the sheet's own bottom
// edge is below the fold. visualViewport is the only signal that tracks this
// correctly on both platforms; `dvh` does not react to the keyboard on iOS
// Safari, which is why the CSS fallback below can't do this job alone.
const viewportHeight = ref(null);
const viewportOffsetTop = ref(0);

function syncViewport() {
    const vv = window.visualViewport;
    if (!vv) {
        return;
    }

    viewportHeight.value = vv.height;
    viewportOffsetTop.value = vv.offsetTop;
}

/** Empty until visualViewport reports — the CSS `h-[100dvh]` fallback holds. */
const overlayStyle = computed(() => (viewportHeight.value === null
    ? {}
    : { height: `${viewportHeight.value}px`, top: `${viewportOffsetTop.value}px` }));

function trackViewport(isOpen) {
    const vv = window.visualViewport;
    if (!vv) {
        return;
    }

    if (isOpen) {
        syncViewport();
        vv.addEventListener('resize', syncViewport);
        vv.addEventListener('scroll', syncViewport);

        return;
    }

    vv.removeEventListener('resize', syncViewport);
    vv.removeEventListener('scroll', syncViewport);
    viewportHeight.value = null;
    viewportOffsetTop.value = 0;
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

        trackViewport(isOpen);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (holdsLock) {
        releaseBodyLock(close);
        holdsLock = false;
    }

    trackViewport(false);
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
                class="fixed left-0 right-0 top-0 z-50 flex h-[100dvh] items-end justify-center bg-[var(--backdrop)]/70"
                :style="overlayStyle"
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
                        class="max-h-[92%] w-full max-w-[480px] overflow-y-auto overscroll-contain rounded-t-3xl bg-[var(--surface)] p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-[0_-10px_40px_rgba(15,23,42,0.2)] min-[700px]:max-w-[600px]"
                    >
                        <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-[var(--border-strong)]" />
                        <slot />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
