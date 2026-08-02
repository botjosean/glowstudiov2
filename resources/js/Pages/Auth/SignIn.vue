<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Input from '../../Components/ui/Input.vue';
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
            <div class="text-xl font-extrabold tracking-tight text-[var(--text-strong)]">
                {{ $t('signIn.title') }}
            </div>
            <div class="mt-1 text-[13px] font-medium text-[var(--text-mute)]">{{ $t('signIn.subtitle') }}</div>

            <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
                <p v-if="form.errors.identifier" class="text-xs font-semibold text-[var(--danger)]">
                    {{ form.errors.identifier }}
                </p>
                <Input v-model="form.identifier" :label="$t('signIn.identifierLabel')" type="text" />
                <label class="flex flex-col gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                        $t('signIn.passwordLabel')
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

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-[18px] w-[18px] rounded-[5px] border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] accent-[var(--btn-green)]"
                        />
                        <span class="text-xs font-semibold text-[var(--text-body)]">{{ $t('signIn.remember') }}</span>
                    </label>
                    <span class="text-xs font-bold text-[var(--green-text)]">{{ $t('signIn.forgot') }}</span>
                </div>

                <div class="mt-2 flex gap-3">
                    <Link
                        href="/"
                        class="flex-1 rounded-xl border-[1.5px] border-[var(--border-strong)] py-3.5 text-center text-sm font-bold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                        >{{ $t('common.cancel') }}</Link
                    >
                    <Button variant="primary" type="submit" :disabled="form.processing" class="flex-1">{{
                        $t('menu.signIn')
                    }}</Button>
                </div>
            </form>

            <div class="mt-4 text-center text-xs font-semibold text-[var(--text-mute)]">
                {{ $t('signIn.noAccount') }}
                <Link href="/crear-cuenta" class="font-bold text-[var(--green-text)]">{{ $t('signIn.createOne') }}</Link>
            </div>
        </div>
    </PublicLayout>
</template>
