<script setup>
import { ref } from 'vue';
import { Pencil, Ban, Plus } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ServiceFormSheet from '../../Components/admin/ServiceFormSheet.vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    services: { type: Array, required: true },
    // [{ id, name, durationMinutes, price, category }]
});

const { formatDuration } = useFormat();

const sheetOpen = ref(false);
const sheetMode = ref('create');
const editingService = ref(null);

function openCreate() {
    sheetMode.value = 'create';
    editingService.value = { name: '', durationMinutes: 45, price: 35, category: 'fade' };
    sheetOpen.value = true;
}

function openEdit(service) {
    sheetMode.value = 'edit';
    editingService.value = service;
    sheetOpen.value = true;
}

function handleSave(values) {
    if (sheetMode.value === 'create') {
        props.services.push({ id: Date.now(), ...values });
    } else {
        const target = props.services.find((s) => s.id === editingService.value.id);
        if (target) Object.assign(target, values);
    }
    sheetOpen.value = false;
}

function handleDelete() {
    const index = props.services.findIndex((s) => s.id === editingService.value.id);
    if (index !== -1) props.services.splice(index, 1);
    sheetOpen.value = false;
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="relative flex flex-col gap-3 p-4">
            <div
                v-for="service in services"
                :key="service.id"
                class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
            >
                <div>
                    <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ service.name }}</div>
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
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-[var(--surface-mute)]"
                        @click="
                            editingService = service;
                            handleDelete();
                        "
                    >
                        <Ban :size="14" class="text-[var(--danger)]" />
                    </button>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="fixed bottom-24 right-4 z-20 flex h-13 w-13 items-center justify-center rounded-full bg-[var(--btn-bg)] shadow-[0_8px_20px_rgba(0,0,0,0.25)] hover:bg-[var(--btn-hover)] sm:absolute"
            @click="openCreate"
        >
            <Plus :size="22" class="text-white" />
        </button>

        <ServiceFormSheet v-model="sheetOpen" :mode="sheetMode" :service="editingService" @save="handleSave" @delete="handleDelete" />
    </AdminLayout>
</template>
