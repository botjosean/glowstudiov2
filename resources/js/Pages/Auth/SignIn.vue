<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import AuthTabs from '../../Components/ui/AuthTabs.vue';
import OutlinedInput from '../../Components/ui/OutlinedInput.vue';
import PasswordField from '../../Components/ui/PasswordField.vue';
import Button from '../../Components/ui/Button.vue';

const form = useForm({
    identifier: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login');
}
</script>

<template>
    <PublicLayout>
        <div class="p-6">
            <AuthTabs active="signin" />

            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('signIn.title') }}
            </h1>
            <div class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">{{ $t('signIn.subtitle') }}</div>

            <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
                <p v-if="form.errors.identifier" class="text-[13px] font-normal text-[var(--danger)]">
                    {{ form.errors.identifier }}
                </p>
                <OutlinedInput id="signin-identifier" v-model="form.identifier" :label="$t('signIn.identifierLabel')" type="text" />
                <PasswordField v-model="form.password" :label="$t('signIn.passwordLabel')" />

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-[18px] w-[18px] rounded-[5px] border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] accent-[var(--btn-green)]"
                        />
                        <span class="text-[13px] font-normal text-[var(--text-body)]">{{ $t('signIn.remember') }}</span>
                    </label>
                    <Link href="/olvide-contrasena" class="text-[13px] font-medium text-[var(--green-text)]">{{
                        $t('signIn.forgot')
                    }}</Link>
                </div>

                <div class="mt-2 flex gap-3">
                    <Link
                        href="/"
                        class="flex-1 rounded-xl border-[1.5px] border-[var(--border-strong)] py-3.5 text-center text-[15px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                        >{{ $t('common.cancel') }}</Link
                    >
                    <Button variant="primary" type="submit" :disabled="form.processing" class="flex-1">{{
                        $t('menu.signIn')
                    }}</Button>
                </div>
            </form>

            <div class="mt-5 flex items-center gap-2.5">
                <div class="h-px flex-1 bg-[var(--border-strong)]" />
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">{{ $t('signIn.orContinueWith') }}</span>
                <div class="h-px flex-1 bg-[var(--border-strong)]" />
            </div>

            <a
                href="/auth/google/redirect"
                class="mt-3.5 flex w-full items-center justify-center gap-2.5 rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface)] py-3.5 text-[15px] font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-mute)]"
            >
                <svg width="18" height="18" viewBox="0 0 48 48">
                    <path
                        fill="#EA4335"
                        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                    />
                    <path
                        fill="#4285F4"
                        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.9-2.26 5.36-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M10.53 28.59A14.5 14.5 0 0 1 9.5 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.94 23.94 0 0 0 0 24c0 3.86.92 7.51 2.56 10.78z"
                    />
                    <path
                        fill="#34A853"
                        d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.9l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                    />
                </svg>
                {{ $t('common.continueWithGoogle') }}
            </a>
        </div>
    </PublicLayout>
</template>
