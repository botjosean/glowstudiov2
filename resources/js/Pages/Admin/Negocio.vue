<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Input from '../../Components/ui/Input.vue';
import Textarea from '../../Components/ui/Textarea.vue';
import ToggleGroup from '../../Components/ui/ToggleGroup.vue';
import Collapse from '../../Components/ui/Collapse.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';
import OptionPicker from '../../Components/ui/OptionPicker.vue';
import BioSuggesterSheet from '../../Components/admin/BioSuggesterSheet.vue';
import { useOnboardingReturn } from '../../composables/useOnboardingReturn';

/**
 * Configuración → Información del negocio: the editing form that used to BE
 * the Perfil tab. The Perfil tab is now the Booksy-style showcase; this page
 * owns the words and switches — name, bio, category, location, publication
 * — and posts to the same endpoints as always.
 */
const props = defineProps({
    profile: { type: Object, required: true },
    // { username, publicName, phone, email, businessCategory,
    //   businessSubcategories, bio, isMobile, homeService, serviceArea,
    //   addressLine, gallery, maxGallery, published, activeServicesCount }
    publicUrl: { type: String, required: true },
});

const { t } = useI18n();
const { returnToInicio } = useOnboardingReturn();

const form = useForm({
    username: props.profile.username,
    publicName: props.profile.publicName,
    phone: props.profile.phone,
    businessCategory: props.profile.businessCategory,
    businessSubcategories: props.profile.businessSubcategories || [],
    bio: props.profile.bio,
    isMobile: props.profile.isMobile,
    homeService: props.profile.homeService,
    serviceArea: props.profile.serviceArea,
    addressLine: props.profile.addressLine,
});

const businessCategoryOptions = computed(() => {
    const option = (value) => ({ value, label: t(`admin.businessCategory_${value}`) });

    return [
        { label: t('admin.businessCategoryGroupBeauty'), options: ['nails', 'hair', 'lashes_brows', 'braids'].map(option) },
        { label: t('admin.businessCategoryGroupBarber'), options: ['barbershop'].map(option) },
        { label: t('admin.businessCategoryGroupWaxMakeup'), options: ['waxing', 'makeup'].map(option) },
        { label: t('admin.businessCategoryGroupSpa'), options: ['spa_massage', 'aesthetics'].map(option) },
        { label: t('admin.businessCategoryGroupBody'), options: ['tattoo_piercing'].map(option) },
        { label: '', options: [option('other')] },
    ];
});

const businessCategoryLabel = computed(() => (form.businessCategory
    ? t(`admin.businessCategory_${form.businessCategory}`)
    : ''));

const businessCategoryPickerOpen = ref(false);

// Mirrors App\Enums\BusinessCategory::subcategories() — kept in sync by hand,
// same convention as businessCategoryOptions above.
const SUBCATEGORIES_BY_CATEGORY = {
    nails: [
        'manicure', 'pedicure', 'acrylic', 'gel', 'dip_powder', 'nail_art', 'extensions',
        'nail_repair', 'russian_manicure', 'chrome_nails', 'polygel', 'paraffin_treatment',
    ],
    hair: [
        'haircut', 'color', 'balayage_highlights', 'blowout_styling', 'keratin_treatment',
        'extensions', 'perm', 'updo', 'deep_conditioning', 'scalp_treatment', 'silk_press',
        'color_correction', 'hair_straightening', 'root_touch_up',
    ],
    barbershop: [
        'haircut', 'haircut_beard', 'beard_design', 'shave', 'skin_fade', 'kids_haircut',
        'line_up', 'hot_towel_shave', 'taper_fade', 'buzz_cut',
    ],
    lashes_brows: [
        'lash_extensions', 'volume_lash_extensions', 'hybrid_lash_extensions', 'lash_lift',
        'lash_removal', 'brow_shaping', 'microblading', 'brow_lamination', 'brow_threading', 'tint',
    ],
    braids: [
        'box_braids', 'knotless_braids', 'cornrows', 'feed_in_braids', 'senegalese_twists',
        'faux_locs', 'goddess_braids', 'crochet_braids', 'twists', 'locs', 'starter_locs',
        'locs_maintenance', 'weave_extensions',
    ],
    waxing: [
        'eyebrow_wax', 'facial_wax', 'leg_wax', 'arm_wax', 'underarm_wax', 'back_wax',
        'chest_wax', 'bikini_wax', 'brazilian_wax', 'full_body_wax', 'sugaring',
        'threading', 'laser_hair_removal',
    ],
    makeup: [
        'bridal_makeup', 'event_makeup', 'everyday_makeup', 'editorial_makeup',
        'makeup_lessons', 'airbrush_makeup', 'makeup_trial', 'special_effects_makeup',
    ],
    spa_massage: [
        'relaxation_massage', 'deep_tissue_massage', 'hot_stone_massage', 'lymphatic_drainage',
        'prenatal_massage', 'reflexology', 'facial', 'body_scrub', 'sports_massage',
        'couples_massage', 'thai_massage', 'shiatsu_massage',
    ],
    aesthetics: [
        'facial_cleansing', 'anti_aging_treatment', 'microdermabrasion', 'chemical_peel',
        'microneedling', 'laser_hair_removal', 'body_contouring', 'botox', 'dermal_filler',
        'dermaplaning', 'led_light_therapy', 'prp_treatment',
    ],
    tattoo_piercing: [
        'tattoo', 'fine_line_tattoo', 'black_grey_tattoo', 'tattoo_touch_up', 'cover_up',
        'tattoo_removal', 'piercing', 'cartilage_piercing', 'septum_piercing', 'permanent_makeup',
    ],
    other: [],
};

const businessSubcategoryOptions = computed(() => (SUBCATEGORIES_BY_CATEGORY[form.businessCategory] || [])
    .map((value) => ({ value, label: t(`admin.businessSubcategory_${value}`) })));

const businessSubcategoryLabel = computed(() => form.businessSubcategories
    .map((value) => t(`admin.businessSubcategory_${value}`))
    .join(', '));

const businessSubcategoryPickerOpen = ref(false);

// A category switch invalidates picks that belonged to the old one — the
// backend rejects them anyway, so drop them here instead of surfacing a
// confusing 422 on save.
watch(() => form.businessCategory, (category) => {
    const valid = SUBCATEGORIES_BY_CATEGORY[category] || [];
    form.businessSubcategories = form.businessSubcategories.filter((value) => valid.includes(value));
});

function submit() {
    form.put('/admin/perfil', { preserveScroll: true, preserveState: true, onSuccess: returnToInicio });
}

// One collapsible per topic; the first incomplete one starts open, and the
// checklist's publish step (?abrir=publicacion) overrides that.
const sectionDone = computed(() => ({
    info: Boolean(props.profile.publicName) && Boolean(props.profile.bio),
    categoria: Boolean(props.profile.businessCategory),
    ubicacion: Boolean(props.profile.isMobile
        ? props.profile.serviceArea
        : (props.profile.homeService
            ? props.profile.addressLine && props.profile.serviceArea
            : props.profile.addressLine)),
    publicacion: props.profile.published,
}));

const page = usePage();

function initialOpenSection() {
    const order = ['info', 'categoria', 'ubicacion', 'publicacion'];
    const asked = order.find((key) => page.url.includes(`abrir=${key}`));

    return asked ?? order.find((key) => !sectionDone.value[key]) ?? null;
}

const openSections = reactive({ info: false, categoria: false, ubicacion: false, publicacion: false });
const first = initialOpenSection();
if (first) openSections[first] = true;

// A validation error inside a collapsed section would be invisible.
const errorSection = {
    username: 'info',
    publicName: 'info',
    phone: 'info',
    bio: 'info',
    businessCategory: 'categoria',
    businessSubcategories: 'categoria',
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
                v-model="openSections.categoria"
                :title="$t('admin.sectionBusinessCategory')"
                :hint="businessCategoryLabel || $t('admin.sectionBusinessCategoryHint')"
                :done="sectionDone.categoria"
            >
                <button
                    id="business-category"
                    type="button"
                    class="relative flex w-full items-center rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-[15px] pr-10 text-left text-[15px] font-semibold text-[var(--text-strong)] transition-[border-color,box-shadow] duration-150 focus:border-[var(--text-strong)] focus:shadow-[inset_0_0_0_1px_var(--text-strong)] focus-visible:outline-none"
                    @click="businessCategoryPickerOpen = true"
                >
                    <span class="truncate" :class="!businessCategoryLabel && 'text-[var(--text-faint)]'">
                        {{ businessCategoryLabel || $t('admin.sectionBusinessCategoryHint') }}
                    </span>
                    <ChevronDown :size="16" class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]" />
                </button>
                <p v-if="form.errors.businessCategory" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                    {{ form.errors.businessCategory }}
                </p>

                <div v-if="form.businessCategory && form.businessCategory !== 'other'" class="mt-4">
                    <p class="mb-1.5 text-[12px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">
                        {{ $t('admin.sectionBusinessSubcategory') }}
                    </p>
                    <button
                        id="business-subcategory"
                        type="button"
                        class="relative flex w-full items-center rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-[15px] pr-10 text-left text-[15px] font-semibold text-[var(--text-strong)] transition-[border-color,box-shadow] duration-150 focus:border-[var(--text-strong)] focus:shadow-[inset_0_0_0_1px_var(--text-strong)] focus-visible:outline-none"
                        @click="businessSubcategoryPickerOpen = true"
                    >
                        <span class="truncate" :class="!businessSubcategoryLabel && 'text-[var(--text-faint)]'">
                            {{ businessSubcategoryLabel || $t('admin.sectionBusinessSubcategoryHint') }}
                        </span>
                        <ChevronDown :size="16" class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]" />
                    </button>
                    <p v-if="form.errors.businessSubcategories" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                        {{ form.errors.businessSubcategories }}
                    </p>
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

        <BottomSheet v-model="businessCategoryPickerOpen">
            <OptionPicker
                v-model="form.businessCategory"
                :options="businessCategoryOptions"
                :title="$t('admin.businessCategory')"
                @close="businessCategoryPickerOpen = false"
            />
        </BottomSheet>

        <BottomSheet v-model="businessSubcategoryPickerOpen">
            <OptionPicker
                v-model="form.businessSubcategories"
                :options="businessSubcategoryOptions"
                :title="$t('admin.businessSubcategoryTitle')"
                multiple
                @close="businessSubcategoryPickerOpen = false"
            />
        </BottomSheet>
    </AdminLayout>
</template>
