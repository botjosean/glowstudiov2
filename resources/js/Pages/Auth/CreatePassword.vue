<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Eye, Check } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Button from '../../Components/ui/Button.vue';

const form = useForm({
    password: '',
    confirmPassword: '',
});

const passwordsMatch = computed(
    () => form.confirmPassword.length > 0 && form.confirmPassword === form.password,
);

const firstError = computed(() => Object.values(form.errors)[0] ?? null);

function submit() {
    form.put('/crear-contrasena');
}
</script>

<template>
    <PublicLayout>
        <div class="p-6">
            <div class="text-xl font-extrabold tracking-tight text-[var(--text-strong)]">
                {{ $t('createPassword.title') }}
            </div>
            <div class="mt-1 text-[13px] font-medium text-[var(--text-mute)]">{{ $t('createPassword.subtitle') }}</div>

            <form class="mt-5 flex flex-col gap-3.5" @submit.prevent="submit">
                <p v-if="firstError" class="text-xs font-semibold text-[var(--danger)]">{{ firstError }}</p>

                <label class="flex flex-col gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                        $t('createPassword.passwordLabel')
                    }}</span>
                    <div
                        class="flex items-center justify-between rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5"
                    >
                        <input
                            v-model="form.password"
                            type="password"
                            class="w-full bg-transparent text-base tracking-[3px] text-[var(--text-strong)] focus:outline-none"
                        />
                        <Eye :size="18" class="shrink-0 text-[var(--text-faint)]" />
                    </div>
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                        $t('createPassword.confirmPasswordLabel')
                    }}</span>
                    <div
                        class="flex items-center justify-between rounded-xl border-[1.5px] px-4 py-3.5"
                        :class="
                            passwordsMatch
                                ? 'border-[var(--green-border)] bg-[var(--green-soft)]'
                                : 'border-[var(--border-strong)] bg-[var(--surface-alt)]'
                        "
                    >
                        <input
                            v-model="form.confirmPassword"
                            type="password"
                            class="w-full bg-transparent text-base tracking-[3px] text-[var(--text-strong)] focus:outline-none"
                        />
                        <Check
                            v-if="passwordsMatch"
                            :size="18"
                            class="shrink-0 text-[var(--green-text)]"
                        />
                        <Eye v-else :size="18" class="shrink-0 text-[var(--text-faint)]" />
                    </div>
                </label>

                <Button variant="primary" type="submit" :disabled="form.processing" class="mt-2">{{
                    $t('createPassword.submit')
                }}</Button>
            </form>
        </div>
    </PublicLayout>
</template>
