<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';

/**
 * Configuración → Asistente de WhatsApp.
 *
 * Everything the assistant says used to be code, so changing how it introduced
 * a professional meant a deploy. It belongs here next to her cover, her hours
 * and her services: it is part of setting up a business.
 *
 * The preview at the bottom is the point of the page. She is writing a message
 * that will land on a stranger's phone, and the placeholders (:profesional,
 * :negocio) are meaningless until she sees them filled in.
 */
const props = defineProps({
    settings: { type: Object, required: true },
    // { mode, displayName, businessName, greeting, greetingReturning, intake,
    //   offersBookingLink, notes } — nulls mean "use the default".
    defaults: { type: Object, required: true },
    // The exact text the server would send if a box is left empty.
    connection: { type: Object, required: true },
    publicUrl: { type: String, required: true },
});

const { t } = useI18n();

const form = useForm({
    mode: props.settings.mode,
    displayName: props.settings.displayName ?? '',
    businessName: props.settings.businessName ?? '',
    greeting: props.settings.greeting ?? '',
    greetingReturning: props.settings.greetingReturning ?? '',
    intake: props.settings.intake ?? '',
    offersBookingLink: props.settings.offersBookingLink,
    notes: props.settings.notes ?? '',
});

function submit() {
    form.put('/admin/asistente', { preserveScroll: true });
}

const modeOptions = computed(() => [
    { value: 'recepcionista', label: t('admin.botModeReceptionist'), hint: t('admin.botModeReceptionistHint') },
    { value: 'agente', label: t('admin.botModeAgent'), hint: t('admin.botModeAgentHint') },
]);

const isReceptionist = computed(() => form.mode === 'recepcionista');

// ---- Preview ---------------------------------------------------------------

// The same substitution the server does, so what she reads here is what a
// client reads on her phone. An example name for the returning greeting: she
// cannot preview "whoever writes next".
function fill(text) {
    return (text || '')
        .replaceAll(':negocio', form.businessName.trim() || props.defaults.businessName)
        .replaceAll(':profesional', form.displayName.trim() || props.defaults.displayName)
        .replaceAll(':nombre', t('admin.botPreviewClientName'))
        .replaceAll(':enlace', props.publicUrl);
}

const previewGreeting = computed(() => fill(form.greeting.trim() || props.defaults.greeting));
const previewReturning = computed(() => fill(form.greetingReturning.trim() || props.defaults.greetingReturning));

const previewIntake = computed(() => {
    const template = form.intake.trim() || props.defaults.intake;
    const body = fill(template);

    // Mirrors Receptionist::intake(): the line is only appended when the
    // message did not already place the link itself.
    return form.offersBookingLink && !template.includes(':enlace')
        ? body + props.defaults.bookingLinkLine
        : body;
});
</script>

<template>
    <AdminLayout>
        <template #header>
            <header class="flex items-center gap-3 border-b border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-3">
                <Link
                    href="/admin/ajustes"
                    :aria-label="$t('admin.settings')"
                    class="-ml-1 flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                >
                    <ArrowLeft :size="20" class="text-[var(--text-strong)]" />
                </Link>
                <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.settingsAssistant') }}</span>
            </header>
        </template>

        <form class="flex flex-col gap-5 p-5 pb-10" @submit.prevent="submit">
            <!-- Without a connected number none of this reaches anybody, and a
                 page that never says so is a page that wastes her afternoon. -->
            <div
                v-if="!connection.connected"
                class="rounded-2xl border border-[#d97706]/40 bg-[#d97706]/5 p-4"
            >
                <div class="flex items-center gap-2">
                    <MessageCircle :size="16" class="text-[#d97706]" />
                    <span class="text-[13px] font-bold text-[var(--text-strong)]">{{ $t('admin.botNotConnected') }}</span>
                </div>
                <p class="mt-1.5 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.botNotConnectedHint') }}</p>
            </div>

            <!-- What it does -->
            <section class="flex flex-col gap-3">
                <h2 class="text-[13px] font-bold uppercase tracking-wide text-[var(--text-mute)]">{{ $t('admin.botWhatItDoes') }}</h2>
                <ToggleGroup v-model="form.mode" :options="modeOptions" />
            </section>

            <!-- Who it says it is -->
            <section class="flex flex-col gap-4">
                <h2 class="text-[13px] font-bold uppercase tracking-wide text-[var(--text-mute)]">{{ $t('admin.botIdentity') }}</h2>
                <div>
                    <Input
                        v-model="form.displayName"
                        :label="$t('admin.botDisplayName')"
                        :placeholder="defaults.displayName"
                    />
                    <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.botDisplayNameHint') }}</p>
                    <p v-if="form.errors.displayName" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.displayName }}</p>
                </div>
                <div>
                    <Input
                        v-model="form.businessName"
                        :label="$t('admin.botBusinessName')"
                        :placeholder="defaults.businessName"
                    />
                    <p v-if="form.errors.businessName" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.businessName }}</p>
                </div>
            </section>

            <!-- The two messages. Only receptionist mode sends them; an agent
                 writes its own, so showing these boxes there would be a lie. -->
            <template v-if="isReceptionist">
                <section class="flex flex-col gap-4">
                    <div>
                        <h2 class="text-[13px] font-bold uppercase tracking-wide text-[var(--text-mute)]">{{ $t('admin.botMessages') }}</h2>
                        <p class="mt-1 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.botMessagesHint') }}</p>
                    </div>
                    <div>
                        <Textarea
                            v-model="form.greeting"
                            :label="$t('admin.botGreeting')"
                            :placeholder="defaults.greeting"
                            :rows="4"
                        />
                        <p v-if="form.errors.greeting" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.greeting }}</p>
                    </div>
                    <div>
                        <Textarea
                            v-model="form.greetingReturning"
                            :label="$t('admin.botGreetingReturning')"
                            :placeholder="defaults.greetingReturning"
                            :rows="4"
                        />
                        <p v-if="form.errors.greetingReturning" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.greetingReturning }}</p>
                    </div>
                    <div>
                        <Textarea
                            v-model="form.intake"
                            :label="$t('admin.botIntake')"
                            :placeholder="defaults.intake"
                            :rows="6"
                        />
                        <p v-if="form.errors.intake" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.intake }}</p>
                    </div>
                    <label class="flex items-start justify-between gap-3 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                        <span class="min-w-0">
                            <span class="block text-[14px] font-semibold text-[var(--text-strong)]">{{ $t('admin.botOffersLink') }}</span>
                            <span class="mt-0.5 block text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.botOffersLinkHint') }}</span>
                        </span>
                        <input v-model="form.offersBookingLink" type="checkbox" class="mt-1 h-5 w-5 shrink-0 accent-[var(--btn-bg)]" />
                    </label>
                </section>

                <!-- The preview: placeholders are meaningless until filled in. -->
                <section class="flex flex-col gap-2">
                    <h2 class="text-[13px] font-bold uppercase tracking-wide text-[var(--text-mute)]">{{ $t('admin.botPreview') }}</h2>
                    <p class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.botPreviewHint') }}</p>
                    <div class="flex flex-col gap-2 rounded-2xl bg-[#0b141a] p-3">
                        <span class="px-1 text-[10px] font-medium uppercase tracking-wide text-white/40">{{ $t('admin.botPreviewNew') }}</span>
                        <p class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-tl-md bg-[#202c33] px-3 py-2 text-[13px] font-normal leading-relaxed text-[#e9edef]">{{ previewGreeting }}</p>
                        <p class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-tl-md bg-[#202c33] px-3 py-2 text-[13px] font-normal leading-relaxed text-[#e9edef]">{{ previewIntake }}</p>
                        <span class="mt-2 px-1 text-[10px] font-medium uppercase tracking-wide text-white/40">{{ $t('admin.botPreviewReturning') }}</span>
                        <p class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-tl-md bg-[#202c33] px-3 py-2 text-[13px] font-normal leading-relaxed text-[#e9edef]">{{ previewReturning }}</p>
                    </div>
                </section>
            </template>

            <!-- Her own notes, which only an agent reads: a receptionist never
                 answers a question, so it has nothing to answer them from. -->
            <section v-else class="flex flex-col gap-2">
                <h2 class="text-[13px] font-bold uppercase tracking-wide text-[var(--text-mute)]">{{ $t('admin.botNotes') }}</h2>
                <p class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.botNotesHint') }}</p>
                <Textarea v-model="form.notes" :rows="8" :placeholder="$t('admin.botNotesPlaceholder')" />
                <p v-if="form.errors.notes" class="text-[13px] font-normal text-[var(--danger)]">{{ form.errors.notes }}</p>
            </section>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:opacity-60"
            >
                {{ $t('admin.saveChanges') }}
            </button>
        </form>
    </AdminLayout>
</template>
