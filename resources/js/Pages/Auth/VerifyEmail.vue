<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { MailCheck, RefreshCw, LogOut } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Button from '../../Components/ui/Button.vue';

defineProps({
    email: { type: String, required: true },
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

            <div class="mt-5 text-xl font-extrabold tracking-tight text-[var(--text-strong)]">
                {{ $t('verifyEmail.title') }}
            </div>
            <div class="mt-1.5 text-[13px] font-medium text-[var(--text-mute)]">
                {{ $t('verifyEmail.subtitle', { email }) }}
            </div>
            <div class="mt-4 text-[13px] font-medium leading-relaxed text-[var(--text-body)]">
                {{ $t('verifyEmail.body') }}
            </div>

            <Button variant="primary" class="mt-6 w-full" :disabled="sending" @click="resend">
                <RefreshCw :size="15" />
                {{ $t('verifyEmail.resend') }}
            </Button>

            <button
                type="button"
                class="mt-4 flex items-center gap-1.5 text-xs font-bold text-[var(--text-mute)] hover:text-[var(--text-strong)]"
                @click="router.post('/logout')"
            >
                <LogOut :size="13" />
                {{ $t('verifyEmail.logOut') }}
            </button>
        </div>
    </PublicLayout>
</template>
