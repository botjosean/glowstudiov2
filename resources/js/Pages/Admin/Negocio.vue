<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';
import Collapse from '../../Components/ui/Collapse.vue';
import BioSuggesterSheet from '../../Components/admin/BioSuggesterSheet.vue';
import { useOnboardingReturn } from '../../composables/useOnboardingReturn';

/**
 * Configuración → Información del negocio: the editing form that used to BE
 * the Perfil tab. The Perfil tab is now the Booksy-style showcase; this page
 * owns the words and switches — name, bio, location, publication — and posts
 * to the same endpoints as always.
 */
const props = defineProps({
    profile: { type: Object, required: true },
    // { username, publicName, phone, email, bio, isMobile, homeService,
    //   serviceArea, addressLine, gallery, maxGallery, published, activeServicesCount }
    publicUrl: { type: String, required: true },
});

const { t } = useI18n();
const { returnToInicio } = useOnboardingReturn();

const form = useForm({
    username: props.profile.username,
    publicName: props.profile.publicName,
    phone: props.profile.phone,
    bio: props.profile.bio,
    isMobile: props.profile.isMobile,
    homeService: props.profile.homeService,
    serviceArea: props.profile.serviceArea,
    addressLine: props.profile.addressLine,
});

function submit() {
    form.put('/admin/perfil', { preserveScroll: true, preserveState: true, onSuccess: returnToInicio });
}

// One collapsible per topic; the first incomplete one starts open, and the
// checklist's publish step (?abrir=publicacion) overrides that.
const sectionDone = computed(() => ({
    info: Boolean(props.profile.publicName) && Boolean(props.profile.bio),
    ubicacion: Boolean(props.profile.isMobile
        ? props.profile.serviceArea
        : (props.profile.homeService
            ? props.profile.addressLine && props.profile.serviceArea
            : props.profile.addressLine)),
    publicacion: props.profile.published,
}));

const page = usePage();

function initialOpenSection() {
    const order = ['info', 'ubicacion', 'publicacion'];
    const asked = order.find((key) => page.url.includes(`abrir=${key}`));

    return asked ?? order.find((key) => !sectionDone.value[key]) ?? null;
}

const openSections = reactive({ info: false, ubicacion: false, publicacion: false });
const first = initialOpenSection();
if (first) openSections[first] = true;

// A validation error inside a collapsed section would be invisible.
const errorSection = {
    username: 'info',
    publicName: 'info',
    phone: 'info',
    bio: 'info',
    serviceArea: 'ubicacion',
    addressLine: 'ubicacion',
};

watch(
    () => form.errors,
    (errors) => {
        for (const field of Object.keys(errors)) {
            const section = errorSection[field];
            if (section) openSections[section] = true;
        }
    },
    { deep: true },
);

const publishOptions = computed(() => [
    { value: 'public', label: t('admin.publishStatePublic') },
    { value: 'hidden', label: t('admin.publishStateHidden') },
]);

const locationOptions = computed(() => [
    { value: 'studio', label: t('admin.locationStudio') },
    { value: 'mobile', label: t('admin.locationMobile') },
    { value: 'both', label: t('admin.locationBoth') },
]);

// Three honest modes over two booleans — see Servicios for the per-service
// home switch; this only says she is willing to travel at all.
const locationValue = computed({
    get: () => (form.isMobile ? 'mobile' : form.homeService ? 'both' : 'studio'),
    set: (value) => {
        form.isMobile = value === 'mobile';
        form.homeService = value === 'both';
    },
});

const canPublish = computed(() => props.profile.published || props.profile.activeServicesCount > 0);
const publishValue = computed(() => (props.profile.published ? 'public' : 'hidden'));
const publishProcessing = ref(false);

function togglePublish(value) {
    publishProcessing.value = true;
    router.patch(
        '/admin/perfil/publicacion',
        { published: value === 'public' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (value === 'public') returnToInicio();
            },
            onFinish: () => { publishProcessing.value = false; },
        },
    );
}

const bioSheetOpen = ref(false);
</script>

<template>
    <AdminLayout :provider-name="profile.publicName">
        <template #header>
            <header class="flex items-center gap-3 border-b border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-3">
                <Link
                    href="/admin/ajustes"
                    :aria-label="$t('admin.settings')"
                    class="-ml-1 flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                >
                    <ArrowLeft :size="20" class="text-[var(--text-strong)]" />
                </Link>
                <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.settingsBusinessInfo') }}</span>
            </header>
        </template>

        <div class="flex flex-col gap-3 p-5 pb-8">
            <Collapse
                v-model="openSections.info"
                :title="$t('admin.sectionInfo')"
                :hint="profile.publicName || $t('admin.sectionInfoHint')"
                :done="sectionDone.info"
            >
                <div class="flex flex-col gap-4">
                    <div>
                        <Input v-model="form.username" :label="$t('admin.username')" />
                        <p v-if="form.errors.username" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.username }}</p>
                    </div>
                    <div>
                        <Input v-model="form.publicName" :label="$t('admin.publicName')" />
                        <p v-if="form.errors.publicName" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.publicName }}</p>
                    </div>
                    <div>
                        <Input v-model="form.phone" :label="$t('admin.phone')" type="tel" />
                        <p v-if="form.errors.phone" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <Input :model-value="profile.email" :label="$t('admin.email')" type="email" disabled />
                        <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.emailReadOnly') }}</p>
                    </div>
                    <div>
                        <Textarea v-model="form.bio" :label="$t('admin.bio')" :rows="4" />
                        <button
                            type="button"
                            class="mt-2 flex items-center gap-1.5 rounded-xl border border-[var(--border-strong)] px-3 py-2 text-[13px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                            @click="bioSheetOpen = true"
                        >
                            <Sparkles :size="14" />
                            {{ form.bio ? $t('admin.bioAiRedo') : $t('admin.bioAiCta') }}
                        </button>
                        <p v-if="form.errors.bio" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.bio }}</p>
                    </div>
                </div>
            </Collapse>

            <Collapse
                v-model="openSections.ubicacion"
                :title="$t('admin.sectionLocation')"
                :hint="(profile.isMobile ? profile.serviceArea : profile.addressLine) || $t('admin.sectionLocationHint')"
                :done="sectionDone.ubicacion"
            >
                <ToggleGroup v-model="locationValue" :options="locationOptions" />
                <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.locationHint') }}</p>

                <div class="mt-3 flex flex-col gap-3">
                    <Input
                        v-if="!form.isMobile"
                        v-model="form.addressLine"
                        :label="$t('admin.addressLine')"
                        :placeholder="$t('admin.addressLinePlaceholder')"
                    />
                    <Input
                        v-if="form.isMobile || form.homeService"
                        v-model="form.serviceArea"
                        :label="$t('admin.serviceArea')"
                        :placeholder="$t('admin.serviceAreaPlaceholder')"
                    />
                    <p v-if="form.homeService && !form.isMobile" class="text-[12px] font-normal text-[var(--text-faint)]">
                        {{ $t('admin.locationBothHint') }}
                    </p>
                    <p v-if="form.errors.serviceArea" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                        {{ form.errors.serviceArea }}
                    </p>
                    <p v-if="form.errors.addressLine" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                        {{ form.errors.addressLine }}
                    </p>
                </div>
            </Collapse>

            <Collapse
                v-model="openSections.publicacion"
                :title="$t('admin.publishProfile')"
                :hint="profile.published ? $t('admin.publishStatePublic') : $t('admin.publishStateHidden')"
                :done="sectionDone.publicacion"
            >
                <ToggleGroup
                    :model-value="publishValue"
                    :options="publishOptions"
                    :disabled="publishProcessing || !canPublish"
                    @update:model-value="togglePublish"
                />
                <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.publishProfileHint') }}</p>
                <p v-if="!canPublish" class="mt-1.5 text-[12px] font-normal text-[var(--amber-text)]">
                    {{ $t('admin.publishBlockedNoServices') }}
                </p>
                <p v-else-if="profile.published && profile.activeServicesCount === 0" class="mt-1.5 text-[12px] font-normal text-[var(--amber-text)]">
                    {{ $t('admin.publishedWithoutServices') }}
                </p>
            </Collapse>

            <button
                type="button"
                :disabled="form.processing"
                class="mt-2 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : $t('admin.saveChanges') }}
            </button>
        </div>

        <BioSuggesterSheet v-model="bioSheetOpen" @use="(text) => { form.bio = text; }" />
    </AdminLayout>
</template>
