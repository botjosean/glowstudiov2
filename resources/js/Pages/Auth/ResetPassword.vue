<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Input from '../../Components/ui/Input.vue';
import PasswordField from '../../Components/ui/PasswordField.vue';
import Button from '../../Components/ui/Button.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const passwordsMatch = computed(
    () => form.password_confirmation.length > 0 && form.password_confirmation === form.password,
);

const firstError = computed(() => Object.values(form.errors)[0] ?? null);

function submit() {
    form.post('/reset-password');
}
</script>

<template>
    <PublicLayout>
        <div class="p-6">
            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('resetPassword.title') }}
            </h1>
            <div class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">
                {{ $t('resetPassword.subtitle', { email: form.email }) }}
            </div>

            <form class="mt-6 flex flex-col gap-3.5" @submit.prevent="submit">
                <p v-if="firstError" class="text-[13px] font-normal text-[var(--danger)]">{{ firstError }}</p>

                <Input v-model="form.email" :label="$t('forgotPassword.emailLabel')" type="email" />

                <PasswordField v-model="form.password" :label="$t('resetPassword.passwordLabel')" />

                <PasswordField
                    v-model="form.password_confirmation"
                    :label="$t('resetPassword.confirmPasswordLabel')"
                    :matched="passwordsMatch"
                />

                <Button variant="primary" type="submit" :disabled="form.processing" class="mt-2">{{
                    $t('resetPassword.submit')
                }}</Button>
            </form>
        </div>
    </PublicLayout>
</template>
