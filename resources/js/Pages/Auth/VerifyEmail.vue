<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { MailCheck, RefreshCw, LogOut } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Button from '../../Components/ui/Button.vue';

defineProps({
    email: { type: String, required: true },
});

// The link is usually clicked in another tab or on the phone, so this page
// re-asks the server on an interval (and when the tab regains focus); once
// verified, the server redirects the visit straight into the panel.
function checkVerified() {
    if (document.visibilityState !== 'visible') return;
    router.visit('/verificar-correo', { preserveScroll: true, preserveState: true });
}

let pollId = null;

onMounted(() => {
    pollId = setInterval(checkVerified, 5000);
    window.addEventListener('focus', checkVerified);
});

onUnmounted(() => {
    clearInterval(pollId);
    window.removeEventListener('focus', checkVerified);
});

const sending = ref(false);

function resend() {
    sending.value = true;
    router.post('/email/verification-notification', {}, {
        preserveScroll: true,
        onFinish: () => {
            sending.value = false;
        },
    });
}
</script>

<template>
    <PublicLayout>
        <div class="flex flex-col items-center p-6 pt-10 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--chip-bg)]">
                <MailCheck :size="28" class="text-[var(--chip-fg)]" />
            </div>

            <div class="mt-5 text-xl font-bold tracking-tight text-[var(--text-strong)]">
                {{ $t('verifyEmail.title') }}
            </div>
            <div class="mt-1.5 text-[13px] font-medium text-[var(--text-mute)]">
                {{ $t('verifyEmail.subtitle', { email }) }}
            </div>
            <div class="mt-4 text-[13px] font-medium leading-relaxed text-[var(--text-body)]">
                {{ $t('verifyEmail.body') }}
            </div>

            <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-[var(--green-border)] bg-[var(--green-soft)] px-3.5 py-1.5">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[var(--green-text)] opacity-60" />
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-[var(--green-text)]" />
                </span>
                <span class="text-[12px] font-medium text-[var(--green-deep)]">{{ $t('verifyEmail.waiting') }}</span>
            </div>

            <Button variant="primary" class="mt-6 w-full" :disabled="sending" @click="resend">
                <RefreshCw :size="15" />
                {{ $t('verifyEmail.resend') }}
            </Button>

            <button
                type="button"
                class="mt-4 flex items-center gap-1.5 text-[13px] font-medium text-[var(--text-mute)] hover:text-[var(--text-strong)]"
                @click="router.post('/logout')"
            >
                <LogOut :size="13" />
                {{ $t('verifyEmail.logOut') }}
            </button>
        </div>
    </PublicLayout>
</template>
