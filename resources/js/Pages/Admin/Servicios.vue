<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Pencil, Ban, Plus, RotateCcw, Scissors } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ServiceFormSheet from '../../Components/admin/ServiceFormSheet.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import Badge from '../../Components/ui/Badge.vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    services: { type: Array, required: true },
    // [{ id, name, durationMinutes, price, category, isActive, upcomingCount }]
});

const { t } = useI18n();
const { formatDuration } = useFormat();

const sheetOpen = ref(false);
const sheetMode = ref('create');
const editingService = ref(null);

// The submission vehicle — ServiceFormSheet manages its own draft state
// internally and emits the final values on @save; this form just posts
// whatever it emits, keeping the sheet's existing @save-with-values
// contract unchanged.
const form = useForm({
    name: '',
    durationMinutes: 45,
    price: 35,
    category: 'fade',
});

function openCreate() {
    sheetMode.value = 'create';
    editingService.value = { name: '', durationMinutes: 45, price: 35, category: 'fade' };
    form.clearErrors();
    sheetOpen.value = true;
}

function openEdit(service) {
    sheetMode.value = 'edit';
    editingService.value = service;
    form.clearErrors();
    sheetOpen.value = true;
}

function handleSave(values) {
    form.name = values.name;
    form.durationMinutes = values.durationMinutes;
    form.price = values.price;
    form.category = values.category;

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            sheetOpen.value = false;
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

function activate(service) {
    router.patch(`/admin/servicios/${service.id}/activar`, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div v-if="services.length === 0" class="flex flex-col items-center px-8 pb-10 pt-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--surface-mute)]">
                <Scissors :size="28" class="text-[var(--text-faint)]" />
            </div>
            <h1 class="mt-5 text-[22px] font-extrabold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.servicesEmptyTitle') }}
            </h1>
            <p class="mt-2 max-w-[280px] text-sm font-medium leading-relaxed text-[var(--text-mute)]">
                {{ $t('admin.servicesEmptyBody') }}
            </p>
            <button
                type="button"
                class="mt-6 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-sm font-bold text-white hover:bg-[var(--btn-hover)]"
                @click="openCreate"
            >
                {{ $t('admin.servicesEmptyCta') }}
            </button>
        </div>

        <div v-else class="relative flex flex-col gap-3 p-4">
            <div class="px-1 pb-1 pt-2">
                <h1 class="text-[22px] font-extrabold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.servicesTitle') }}
                </h1>
                <p class="mt-1 text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.servicesSubtitle') }}</p>
            </div>
            <div
                v-for="service in services"
                :key="service.id"
                class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
                :class="!service.isActive && 'opacity-60'"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-extrabold text-[var(--text-strong)]">{{ service.name }}</span>
                        <Badge v-if="!service.isActive" variant="closed">{{ $t('admin.serviceInactive') }}</Badge>
                    </div>
                    <div class="mt-1 text-xs font-semibold text-[var(--text-mute)]">
                        {{ formatDuration(service.durationMinutes) }} · ${{ service.price }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-[var(--surface-mute)]"
                        @click="openEdit(service)"
                    >
                        <Pencil :size="14" class="text-[var(--text-mute)]" />
                    </button>
                    <button
                        v-if="service.isActive"
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-[var(--surface-mute)]"
                        @click="askDeactivate(service)"
                    >
                        <Ban :size="14" class="text-[var(--danger)]" />
                    </button>
                    <button
                        v-else
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-[var(--surface-mute)]"
                        @click="activate(service)"
                    >
                        <RotateCcw :size="14" class="text-[var(--green-text)]" />
                    </button>
                </div>
            </div>
        </div>

        <button
            v-if="services.length > 0"
            type="button"
            class="fixed bottom-24 right-4 z-20 flex h-13 w-13 items-center justify-center rounded-full bg-[var(--btn-bg)] shadow-[0_8px_20px_rgba(0,0,0,0.25)] hover:bg-[var(--btn-hover)] sm:absolute"
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
