<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Download, X } from '@lucide/vue';

/**
 * Our own "install the app" bar, instead of whatever the browser decides.
 *
 * Chrome's built-in suggestion is the reason the icon on a phone could end up
 * pointing at whatever page happened to be open: "add to home screen" saves
 * the current URL, not the app. This asks properly, and installs the manifest
 * — so it always opens where it should.
 *
 * **It cannot nag somebody who already installed it.** `beforeinstallprompt`
 * simply does not fire once the app is installed, so there is nothing to show;
 * on top of that the bar hides itself when the page is already running inside
 * the installed app, and remembers a dismissal so a "no thanks" lasts.
 */
const deferred = ref(null);
const visible = ref(false);

const DISMISSED = 'glow.installDismissed';

// Running inside the installed app already — nothing to offer.
function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        // iOS does not implement display-mode and uses this instead.
        || window.navigator.standalone === true;
}

function onBeforeInstallPrompt(event) {
    // Only fires when the app is installable and NOT yet installed.
    event.preventDefault();

    if (isStandalone() || localStorage.getItem(DISMISSED) === '1') {
        return;
    }

    deferred.value = event;
    visible.value = true;
}

function onInstalled() {
    // Installed from our bar or from the browser menu — either way, done.
    visible.value = false;
    deferred.value = null;
    localStorage.setItem(DISMISSED, '1');
}

async function install() {
    if (!deferred.value) {
        return;
    }

    const prompt = deferred.value;
    // A deferred prompt can only be used once, so it goes before the await.
    deferred.value = null;
    visible.value = false;

    prompt.prompt();

    const choice = await prompt.userChoice;

    // Declining is not the same as dismissing our bar: the browser will offer
    // again on a later visit, and so will we.
    if (choice?.outcome === 'accepted') {
        localStorage.setItem(DISMISSED, '1');
    }
}

function dismiss() {
    visible.value = false;
    deferred.value = null;
    localStorage.setItem(DISMISSED, '1');
}

onMounted(() => {
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onInstalled);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onInstalled);
});
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300"
        leave-active-class="transition-transform duration-200"
        enter-from-class="-translate-y-full"
        leave-to-class="-translate-y-full"
    >
        <div
            v-if="visible"
            class="sticky top-0 z-40 flex items-center gap-3 border-b border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-2.5"
        >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[var(--surface-mute)]">
                <Download :size="17" class="text-[var(--text-strong)]" />
            </div>
            <p class="min-w-0 flex-1 text-[13px] font-medium leading-snug text-[var(--text-strong)]">
                {{ $t('common.installPrompt') }}
            </p>
            <button
                type="button"
                class="shrink-0 rounded-lg bg-[var(--btn-bg)] px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                @click="install"
            >
                {{ $t('common.install') }}
            </button>
            <button
                type="button"
                :aria-label="$t('common.close')"
                class="-mr-1 shrink-0 rounded-lg p-1.5 text-[var(--text-faint)] hover:bg-[var(--surface-mute)]"
                @click="dismiss"
            >
                <X :size="16" />
            </button>
        </div>
    </Transition>
</template>
