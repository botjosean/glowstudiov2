<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Button from '../../Components/ui/Button.vue';

const form = useForm({
    email: '',
});

function submit() {
    form.post('/forgot-password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <PublicLayout>
        <div class="p-6">
            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('forgotPassword.title') }}
            </h1>
            <div class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">
                {{ $t('forgotPassword.subtitle') }}
            </div>

            <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
                <p v-if="form.errors.email" class="text-[13px] font-normal text-[var(--danger)]">
                    {{ form.errors.email }}
                </p>
                <Input v-model="form.email" :label="$t('forgotPassword.emailLabel')" type="email" />

                <div class="mt-2 flex gap-3">
                    <Link
                        href="/iniciar-sesion"
                        class="flex-1 rounded-xl border-[1.5px] border-[var(--border-strong)] py-3.5 text-center text-[15px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                        >{{ $t('common.cancel') }}</Link
                    >
                    <Button variant="primary" type="submit" :disabled="form.processing" class="flex-1">{{
                        $t('forgotPassword.submit')
                    }}</Button>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
