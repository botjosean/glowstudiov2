<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus, Sparkles, ChevronRight, House } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ServiceFormSheet from '../../Components/admin/ServiceFormSheet.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import Badge from '../../Components/ui/Badge.vue';
import { categoryIcons } from '../../icons';
import { useFormat } from '../../composables/useFormat';
import { useOnboardingReturn } from '../../composables/useOnboardingReturn';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    services: { type: Array, required: true },
    // [{ id, name, durationMinutes, price, category, isActive, upcomingCount }]
});

const { t } = useI18n();
const { formatDuration } = useFormat();
const { returnToInicio } = useOnboardingReturn();

const sheetOpen = ref(false);
const sheetMode = ref('create');
const editingService = ref(null);

function iconFor(category) {
    return categoryIcons[category] ?? categoryIcons.other;
}

// The submission vehicle — ServiceFormSheet manages its own draft state
// internally and emits the final values on @save; this form just posts
// whatever it emits, keeping the sheet's existing @save-with-values
// contract unchanged.
const form = useForm({
    name: '',
    durationMinutes: 45,
    price: 35,
    category: 'other',
    homeAvailable: false,
});

// "other" rather than a barbershop category: this form is used by nail techs
// and stylists too, and defaulting them into "Fade" was the exact complaint
// that started this work.
const BLANK_SERVICE = { name: '', durationMinutes: 45, price: 35, category: 'other', homeAvailable: false };

function openCreate() {
    sheetMode.value = 'create';
    editingService.value = { ...BLANK_SERVICE };
    form.clearErrors();
    sheetOpen.value = true;
}

function openEdit(service) {
    sheetMode.value = 'edit';
    editingService.value = service;
    form.clearErrors();
    sheetOpen.value = true;
}

function handleSave(values, keepOpen = false) {
    form.name = values.name;
    form.durationMinutes = values.durationMinutes;
    form.price = values.price;
    form.category = values.category;
    form.homeAvailable = values.homeAvailable ?? false;

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (keepOpen) {
                // Hand the sheet a fresh draft rather than closing it. The name
                // clears — every service needs its own — while duration, price
                // and category carry over, because someone loading a batch is
                // normally working through variations of the same thing. No
                // returnToInicio() here: bouncing back to the checklist is the
                // exact interruption this button exists to avoid.
                editingService.value = { ...values, name: '' };

                return;
            }

            sheetOpen.value = false;
            if (sheetMode.value === 'create') {
                returnToInicio();
            }
        },
    };

    if (sheetMode.value === 'create') {
        form.post('/admin/servicios', options);
    } else {
        form.put(`/admin/servicios/${editingService.value.id}`, options);
    }
}

const confirmOpen = ref(false);
const confirmProcessing = ref(false);
const serviceToDeactivate = ref(null);

const deactivateDetail = computed(() => {
    const count = serviceToDeactivate.value?.upcomingCount ?? 0;
    return count > 0 ? t('admin.confirmDeactivateUpcoming', { count }) : '';
});

function askDeactivate(service) {
    serviceToDeactivate.value = service;
    confirmOpen.value = true;
}

// From ServiceFormSheet's @delete: close the form sheet first, then open
// the confirmation dialog — stacking BottomSheets corrupts their shared
// body-scroll-lock watcher.
function requestDeactivateFromSheet() {
    sheetOpen.value = false;
    askDeactivate(editingService.value);
}

function confirmDeactivate() {
    if (!serviceToDeactivate.value) return;

    confirmProcessing.value = true;
    router.patch(`/admin/servicios/${serviceToDeactivate.value.id}/desactivar`, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            confirmProcessing.value = false;
            confirmOpen.value = false;
        },
    });
}

// From ServiceFormSheet's @activate (edit mode on an inactive service):
// reactivating needs no confirmation — it only restores visibility.
function activateFromSheet() {
    sheetOpen.value = false;
    router.patch(`/admin/servicios/${editingService.value.id}/activar`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div v-if="services.length === 0" class="flex flex-col items-center px-8 pb-10 pt-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--gold-soft)]">
                <Sparkles :size="28" class="text-[var(--gold)]" />
            </div>
            <h1 class="mt-5 text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.servicesEmptyTitle') }}
            </h1>
            <p class="mt-2 max-w-[280px] text-[15px] font-normal leading-relaxed text-[var(--text-mute)]">
                {{ $t('admin.servicesEmptyBody') }}
            </p>
            <button
                type="button"
                class="mt-7 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                @click="openCreate"
            >
                {{ $t('admin.servicesEmptyCta') }}
            </button>
        </div>

        <div v-else class="relative px-4 pb-4">
            <div class="pb-2 pt-5">
                <h1 class="text-[24px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.servicesTitle') }}
                </h1>
                <p class="mt-1 text-[15px] font-normal text-[var(--text-mute)]">{{ $t('admin.servicesSubtitle') }}</p>
            </div>

            <div class="divide-y divide-[var(--surface-mute)]">
                <button
                    v-for="service in services"
                    :key="service.id"
                    type="button"
                    class="flex w-full items-center gap-3.5 py-4 text-left hover:bg-[var(--surface-alt)]"
                    @click="openEdit(service)"
                >
                    <component
                        :is="iconFor(service.category)"
                        :size="20"
                        :stroke-width="1.8"
                        class="shrink-0"
                        :class="service.isActive ? 'text-[var(--text-mute)]' : 'text-[var(--text-faint)]'"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span
                                class="truncate text-[15px] font-semibold"
                                :class="service.isActive ? 'text-[var(--text-strong)]' : 'text-[var(--text-faint)]'"
                            >
                                {{ service.name }}
                            </span>
                            <Badge v-if="!service.isActive" variant="closed">{{ $t('admin.serviceInactive') }}</Badge>
                        </span>
                        <span class="mt-0.5 flex items-center gap-1 text-[13px] font-normal text-[var(--text-mute)]">
                            {{ formatDuration(service.durationMinutes) }}
                            <template v-if="service.homeAvailable">
                                <span aria-hidden="true">·</span>
                                <House :size="12" :stroke-width="1.8" class="text-[var(--text-faint)]" />
                                <span class="sr-only">{{ $t('admin.serviceHomeAvailable') }}</span>
                            </template>
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-1.5">
                        <span
                            class="text-[15px] font-bold"
                            :class="service.isActive ? 'text-[var(--text-strong)]' : 'text-[var(--text-faint)]'"
                        >
                            ${{ service.price }}
                        </span>
                        <ChevronRight :size="16" class="text-[var(--text-faint)]" />
                    </span>
                </button>
            </div>
        </div>

        <!-- Fixed on every viewport; on wide screens the right offset pins it
             to the centered 480px column's edge instead of the viewport's,
             because no positioned ancestor exists inside <main> for an
             absolute FAB to anchor to. -->
        <button
            v-if="services.length > 0"
            type="button"
            :aria-label="$t('admin.servicesEmptyCta')"
            class="fixed bottom-24 right-4 z-20 flex h-13 w-13 items-center justify-center rounded-full bg-[var(--btn-bg)] shadow-[0_8px_20px_rgba(0,0,0,0.25)] hover:bg-[var(--btn-hover)] sm:right-[calc(50vw-224px)] md:right-[calc(50vw-394px)] lg:right-[calc(50vw-496px)]"
            @click="openCreate"
        >
            <Plus :size="22" class="text-white" />
        </button>

        <ServiceFormSheet
            v-model="sheetOpen"
            :mode="sheetMode"
            :service="editingService"
            :errors="form.errors"
            :processing="form.processing"
            @save="handleSave"
            @delete="requestDeactivateFromSheet"
            @activate="activateFromSheet"
        />

        <ConfirmDialog
            v-model="confirmOpen"
            :title="$t('admin.confirmDeactivateTitle')"
            :body="$t('admin.confirmDeactivateBody')"
            :detail="deactivateDetail"
            :confirm-label="$t('admin.deactivateService')"
            :processing="confirmProcessing"
            variant="danger"
            @confirm="confirmDeactivate"
        />
    </AdminLayout>
</template>
