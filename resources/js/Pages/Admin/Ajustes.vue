<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, Globe, Clock4, Sun, Moon, Check, LogOut, Info, MessageCircle } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';
import { useTheme } from '../../composables/useTheme';
import { usePreferences } from '../../composables/usePreferences';

defineProps({
    providerName: { type: String, default: 'Pati' },
});

const { t } = useI18n();
const { theme, setTheme } = useTheme();
const { locale, setLanguage, timeFormat, setTimeFormat, whatsappPrompt, setWhatsappPrompt } = usePreferences();

const languageOptions = computed(() => [
    { value: 'es', label: 'Español' },
    { value: 'en', label: 'English' },
]);

const timeFormatOptions = computed(() => [
    { value: '12', label: '12 horas', hint: '4:00 PM' },
    { value: '24', label: '24 horas', hint: '16:00' },
]);

const whatsappPromptOptions = computed(() => [
    { value: 'ask', label: t('admin.waPromptStateAsk') },
    { value: 'never', label: t('admin.waPromptStateNever') },
]);
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <template #header>
            <header class="flex items-center gap-3 border-b border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <Link
                    href="/admin/citas"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                >
                    <ArrowLeft :size="16" class="text-[var(--text-mute)]" />
                </Link>
                <span class="text-base font-extrabold tracking-tight text-[var(--text-strong)]">{{ $t('admin.settings') }}</span>
            </header>
        </template>

        <div class="flex flex-col gap-4 p-5 pb-7">
            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--chip-bg)]">
                        <Globe :size="16" class="text-[var(--chip-fg)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.language') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.languageHint') }}</div>
                    </div>
                </div>
                <ToggleGroup :model-value="locale" :options="languageOptions" @update:model-value="setLanguage" />
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--chip-bg)]">
                        <Clock4 :size="16" class="text-[var(--chip-fg)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.timeFormat') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.timeFormatHint') }}</div>
                    </div>
                </div>
                <ToggleGroup :model-value="timeFormat" :options="timeFormatOptions" @update:model-value="setTimeFormat" />
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--chip-bg)]">
                        <MessageCircle :size="16" class="text-[var(--chip-fg)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.waPromptSetting') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.waPromptSettingHint') }}</div>
                    </div>
                </div>
                <ToggleGroup :model-value="whatsappPrompt" :options="whatsappPromptOptions" @update:model-value="setWhatsappPrompt" />
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--chip-bg)]">
                        <Sun :size="16" class="text-[var(--chip-fg)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.appearance') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.appearanceHint') }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <button
                        type="button"
                        class="rounded-2xl border p-2.5"
                        :class="theme === 'light' ? 'border-[var(--border-strong)]' : 'border-[var(--border-strong)]'"
                        @click="setTheme('light')"
                    >
                        <div class="h-14 overflow-hidden rounded-[10px] border border-[#e2e8f0] bg-[#f1f5f9]" />
                        <div class="mt-2 flex items-center justify-center gap-1.5">
                            <Sun :size="12" class="text-[var(--text-mute)]" />
                            <span
                                class="text-xs"
                                :class="theme === 'light' ? 'font-extrabold text-[var(--text-strong)]' : 'font-bold text-[var(--text-mute)]'"
                                >{{ $t('admin.light') }}</span
                            >
                        </div>
                    </button>
                    <button
                        type="button"
                        class="relative rounded-2xl border p-2.5"
                        :class="theme === 'dark' ? 'border-2 border-[var(--green-text)]' : 'border-[var(--border-strong)]'"
                        @click="setTheme('dark')"
                    >
                        <span
                            v-if="theme === 'dark'"
                            class="absolute right-1.5 top-1.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-[var(--green-text)]"
                        >
                            <Check :size="10" class="text-white" />
                        </span>
                        <div class="h-14 overflow-hidden rounded-[10px] border border-[#232b3b] bg-[#0a0e16]" />
                        <div class="mt-2 flex items-center justify-center gap-1.5">
                            <Moon :size="12" class="text-[var(--text-strong)]" />
                            <span
                                class="text-xs"
                                :class="theme === 'dark' ? 'font-extrabold text-[var(--text-strong)]' : 'font-bold text-[var(--text-mute)]'"
                                >{{ $t('admin.dark') }}</span
                            >
                        </div>
                    </button>
                </div>
            </div>

            <div class="flex items-start gap-2.5 rounded-2xl border border-[var(--green-border)] bg-[var(--green-soft)] p-4">
                <Info :size="16" class="mt-0.5 shrink-0 text-[var(--green-text)]" />
                <div class="text-xs font-semibold leading-relaxed text-[var(--green-deep)]">{{ $t('admin.settingsScope') }}</div>
            </div>

            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--danger-border)] py-3.5 text-sm font-bold text-[var(--danger)] hover:bg-[var(--danger-hover)]"
                @click="router.post('/logout')"
            >
                <LogOut :size="16" />
                {{ $t('admin.logOut') }}
            </button>
        </div>
    </AdminLayout>
</template>
