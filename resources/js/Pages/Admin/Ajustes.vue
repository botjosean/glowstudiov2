<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    ArrowLeft,
    Globe,
    Clock4,
    Sun,
    Moon,
    Check,
    LogOut,
    MessageCircle,
    ChevronRight,
    Search,
    Store,
    Sparkles,
    CalendarClock,
    FileText,
    ShieldCheck,
    House,
} from '@lucide/vue';
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

// Servicios and Horario left the bottom bar when it went to four tabs — this
// hub is now their front door (the agenda's gear still shortcuts to Horario).
const businessLinks = [
    { href: '/admin/negocio', icon: Store, titleKey: 'admin.settingsBusinessInfo', hintKey: 'admin.settingsBusinessInfoHint' },
    { href: '/admin/servicios', icon: Sparkles, titleKey: 'nav.services', hintKey: 'admin.servicesSubtitle' },
    { href: '/admin/asistente', icon: MessageCircle, titleKey: 'admin.settingsAssistant', hintKey: 'admin.settingsAssistantHint' },
    { href: '/admin/horario', icon: CalendarClock, titleKey: 'nav.schedule', hintKey: 'admin.settingsScheduleHint' },
    { href: '/admin/inicio', icon: House, titleKey: 'admin.settingsActivation', hintKey: 'admin.settingsActivationHint' },
];

const legalLinks = [
    { href: '/terminos', icon: FileText, titleKey: 'legal.termsTitle' },
    { href: '/privacidad', icon: ShieldCheck, titleKey: 'legal.privacyTitle' },
];

// Booksy's settings search: filters the hub's rows by their visible title.
// While a query is active the preference cards step aside — the searcher is
// looking for a door, not a toggle.
const search = ref('');

function matches(titleKey) {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') return true;
    return t(titleKey).toLowerCase().includes(needle);
}

const filteredBusinessLinks = computed(() => businessLinks.filter((item) => matches(item.titleKey)));
const filteredLegalLinks = computed(() => legalLinks.filter((item) => matches(item.titleKey)));
const searching = computed(() => search.value.trim() !== '');
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <template #header>
            <header class="flex items-center gap-3 border-b border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-3">
                <Link
                    href="/admin/citas"
                    class="-ml-1 flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                >
                    <ArrowLeft :size="20" class="text-[var(--text-strong)]" />
                </Link>
                <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.settings') }}</span>
            </header>
        </template>

        <div class="flex flex-col gap-6 p-5 pb-8">
            <label class="flex items-center gap-2.5 rounded-xl bg-[var(--surface-mute)] px-3.5 py-2.5">
                <Search :size="17" class="shrink-0 text-[var(--text-faint)]" />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('admin.settingsSearch')"
                    class="w-full bg-transparent text-[15px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:outline-none"
                />
            </label>

            <section v-if="filteredBusinessLinks.length">
                <h2 class="mb-2 px-1 text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.settingsBusiness') }}</h2>
                <div class="overflow-hidden rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)]">
                    <Link
                        v-for="(item, index) in filteredBusinessLinks"
                        :key="item.href"
                        :href="item.href"
                        class="flex w-full items-center gap-3.5 px-4 py-4 hover:bg-[var(--surface-alt)]"
                        :class="index > 0 && 'border-t border-[var(--surface-mute)]'"
                    >
                        <component :is="item.icon" :size="19" class="shrink-0 text-[var(--text-mute)]" :stroke-width="1.8" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-semibold text-[var(--text-strong)]">{{ $t(item.titleKey) }}</span>
                            <span class="mt-0.5 block truncate text-[13px] font-normal text-[var(--text-mute)]">{{
                                $t(item.hintKey)
                            }}</span>
                        </span>
                        <ChevronRight :size="17" class="shrink-0 text-[var(--text-faint)]" />
                    </Link>
                </div>
            </section>

            <section v-show="!searching">
                <h2 class="mb-2 px-1 text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.settingsPreferences') }}</h2>
                <div class="flex flex-col gap-3">
                    <div class="rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] p-4">
                        <div class="mb-3 flex items-center gap-2.5">
                            <Globe :size="17" class="text-[var(--text-mute)]" :stroke-width="1.8" />
                            <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.language') }}</div>
                        </div>
                        <ToggleGroup :model-value="locale" :options="languageOptions" @update:model-value="setLanguage" />
                    </div>

                    <div class="rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] p-4">
                        <div class="mb-3 flex items-center gap-2.5">
                            <Clock4 :size="17" class="text-[var(--text-mute)]" :stroke-width="1.8" />
                            <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.timeFormat') }}</div>
                        </div>
                        <ToggleGroup :model-value="timeFormat" :options="timeFormatOptions" @update:model-value="setTimeFormat" />
                    </div>

                    <div class="rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] p-4">
                        <div class="mb-3 flex items-center gap-2.5">
                            <MessageCircle :size="17" class="text-[var(--text-mute)]" :stroke-width="1.8" />
                            <div>
                                <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.waPromptSetting') }}</div>
                                <div class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.waPromptSettingHint') }}</div>
                            </div>
                        </div>
                        <ToggleGroup :model-value="whatsappPrompt" :options="whatsappPromptOptions" @update:model-value="setWhatsappPrompt" />
                    </div>

                    <div class="rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] p-4">
                        <div class="mb-3 flex items-center gap-2.5">
                            <Sun :size="17" class="text-[var(--text-mute)]" :stroke-width="1.8" />
                            <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.appearance') }}</div>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                type="button"
                                class="relative rounded-xl border p-2.5"
                                :class="theme === 'light' ? 'border-[var(--text-strong)]' : 'border-[var(--border-strong)]'"
                                @click="setTheme('light')"
                            >
                                <span
                                    v-if="theme === 'light'"
                                    class="absolute right-1.5 top-1.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-[var(--text-strong)]"
                                >
                                    <Check :size="10" class="text-[var(--surface)]" />
                                </span>
                                <div class="h-12 overflow-hidden rounded-lg border border-[#e5e7eb] bg-[#f9fafb]" />
                                <div class="mt-2 flex items-center justify-center gap-1.5">
                                    <Sun :size="12" class="text-[var(--text-mute)]" />
                                    <span class="text-[13px] font-medium text-[var(--text-strong)]">{{ $t('admin.light') }}</span>
                                </div>
                            </button>
                            <button
                                type="button"
                                class="relative rounded-xl border p-2.5"
                                :class="theme === 'dark' ? 'border-[var(--text-strong)]' : 'border-[var(--border-strong)]'"
                                @click="setTheme('dark')"
                            >
                                <span
                                    v-if="theme === 'dark'"
                                    class="absolute right-1.5 top-1.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-[var(--text-strong)]"
                                >
                                    <Check :size="10" class="text-[var(--surface)]" />
                                </span>
                                <div class="h-12 overflow-hidden rounded-lg border border-[#2b3340] bg-[#0d1117]" />
                                <div class="mt-2 flex items-center justify-center gap-1.5">
                                    <Moon :size="12" class="text-[var(--text-mute)]" />
                                    <span class="text-[13px] font-medium text-[var(--text-strong)]">{{ $t('admin.dark') }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="filteredLegalLinks.length">
                <h2 class="mb-2 px-1 text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.settingsLegal') }}</h2>
                <div class="overflow-hidden rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)]">
                    <Link
                        v-for="(item, index) in filteredLegalLinks"
                        :key="item.href"
                        :href="item.href"
                        class="flex w-full items-center gap-3.5 px-4 py-4 hover:bg-[var(--surface-alt)]"
                        :class="index > 0 && 'border-t border-[var(--surface-mute)]'"
                    >
                        <component :is="item.icon" :size="19" class="shrink-0 text-[var(--text-mute)]" :stroke-width="1.8" />
                        <span class="flex-1 text-[15px] font-semibold text-[var(--text-strong)]">{{ $t(item.titleKey) }}</span>
                        <ChevronRight :size="17" class="shrink-0 text-[var(--text-faint)]" />
                    </Link>
                    <div class="border-t border-[var(--surface-mute)] px-4 py-3.5 text-[13px] font-normal text-[var(--text-faint)]">
                        {{ $t('app.name') }} · {{ $t('admin.settingsVersion') }} 2.0
                    </div>
                </div>
            </section>

            <p class="px-1 text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">{{ $t('admin.settingsScope') }}</p>

            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--danger-border)] py-3.5 text-[15px] font-semibold text-[var(--danger)] hover:bg-[var(--danger-hover)]"
                @click="router.post('/logout')"
            >
                <LogOut :size="16" />
                {{ $t('admin.logOut') }}
            </button>
        </div>
    </AdminLayout>
</template>
