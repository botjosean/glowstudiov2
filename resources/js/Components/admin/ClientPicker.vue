<script setup>
/**
 * Elegir una clienta: un solo buscador para toda la app.
 *
 * Existían dos formas de hacer exactamente lo mismo. Al crear una cita salía
 * esto —buscador, inicial en un círculo, teléfono debajo—, y al cobrar salía
 * un `<select>` normal, que en Android abre un menú marrón con radios que no
 * se parece a nada del resto de la app. Ese menú **no lo dibuja la app**: lo
 * dibuja el sistema, y por eso ignora los colores, la tipografía y el redondeo
 * de todo lo demás.
 *
 * Peor que feo: un `<select>` es una lista plana sin buscar. Con veinte
 * clientas ya es incómodo, y la libreta solo crece.
 *
 * Así que este componente es el de crear cita, sacado a un sitio común. No es
 * un diseño nuevo — es el que ya le gustaba, puesto también donde faltaba.
 *
 * **Se ocupa de toda la pantalla mientras busca**, igual que hacía antes: por
 * eso lleva `v-model:open` y quien lo usa esconde su propio contenido mientras
 * está abierto. Un desplegable flotante encima de una hoja que ya está encima
 * de la página son tres capas, y en un teléfono eso se cierra solo sin querer.
 */
import { computed, ref } from 'vue';
import { ArrowLeft, ChevronRight, Plus, Search, UserRound, X } from '@lucide/vue';

const props = defineProps({
    clients: { type: Array, required: true },
    // [{ id, name, phone? }]
    placeholder: { type: String, default: '' },
    // Texto del botón cuando no hay nadie elegido.
    addLabel: { type: String, default: '' },
    // Texto de la fila "+ añadir". Si va vacío, esa fila no sale.
});

// La clienta elegida entera, no su id: quien lo usa casi siempre necesita
// también el nombre y el teléfono, y volver a buscarlos por id es trabajo
// que este componente ya hizo.
const selected = defineModel({ type: Object, default: null });
const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['add-new']);

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (q === '') return props.clients;

    // Por nombre y por teléfono: ella se acuerda de uno o del otro.
    return props.clients.filter((c) =>
        c.name.toLowerCase().includes(q)
        || (c.phone ?? '').replace(/\D/g, '').includes(q.replace(/\D/g, '')),
    );
});

function pick(client) {
    selected.value = client;
    search.value = '';
    open.value = false;
}

function clear() {
    selected.value = null;
}

function addNew() {
    search.value = '';
    open.value = false;
    emit('add-new');
}
</script>

<template>
    <!-- Buscando: ocupa la pantalla -->
    <div v-if="open">
        <div class="mb-4 flex items-center gap-3">
            <button
                type="button"
                :aria-label="$t('common.cancel')"
                class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                @click="open = false"
            >
                <ArrowLeft :size="19" class="text-[var(--text-strong)]" />
            </button>
            <span class="text-[15px] font-bold text-[var(--text-strong)]">{{ $t('admin.selectClient') }}</span>
        </div>

        <label class="mb-2 flex items-center gap-2.5 rounded-xl bg-[var(--surface-mute)] px-3.5 py-2.5">
            <Search :size="16" class="shrink-0 text-[var(--text-faint)]" />
            <input
                v-model="search"
                type="search"
                :placeholder="$t('admin.clientsSearch')"
                class="w-full bg-transparent text-[15px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:outline-none"
            />
        </label>

        <button
            v-if="addLabel"
            type="button"
            class="flex w-full items-center gap-3 border-b border-[var(--surface-mute)] px-1 py-3 text-left hover:bg-[var(--surface-alt)]"
            @click="addNew"
        >
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--surface-mute)]">
                <Plus :size="16" class="text-[var(--text-strong)]" />
            </span>
            <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ addLabel }}</span>
        </button>

        <div class="max-h-[300px] overflow-y-auto">
            <button
                v-for="client in filtered"
                :key="client.id"
                type="button"
                class="flex w-full items-center gap-3 border-b border-[var(--surface-mute)] px-1 py-3 text-left last:border-b-0 hover:bg-[var(--surface-alt)]"
                @click="pick(client)"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--surface-mute)] text-[13px] font-bold text-[var(--text-mute)]">
                    {{ client.name[0]?.toUpperCase() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[15px] font-semibold text-[var(--text-strong)]">{{ client.name }}</span>
                    <span class="block text-[13px] font-normal text-[var(--text-mute)]">{{ client.phone ?? '—' }}</span>
                </span>
                <ChevronRight :size="15" class="shrink-0 text-[var(--text-faint)]" />
            </button>

            <p v-if="filtered.length === 0" class="py-6 text-center text-[13px] font-normal text-[var(--text-mute)]">
                {{ $t('admin.clientsEmpty') }}
            </p>
        </div>
    </div>

    <!-- Cerrado: o el botón para abrirlo, o la elegida -->
    <template v-else>
        <button
            v-if="!selected"
            type="button"
            class="flex w-full items-center gap-3.5 rounded-2xl border border-dashed border-[var(--border-strong)] px-4 py-4 text-left hover:bg-[var(--surface-alt)]"
            @click="open = true"
        >
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[var(--surface-mute)] bg-[var(--surface-mute)]">
                <UserRound :size="20" class="text-[var(--text-faint)]" />
            </span>
            <span class="flex-1 text-[15px] font-medium text-[var(--text-mute)]">
                {{ placeholder || $t('admin.selectClient') }}
            </span>
            <ChevronRight :size="17" class="shrink-0 text-[var(--text-faint)]" />
        </button>

        <div
            v-else
            class="flex items-center gap-3.5 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-3.5"
        >
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--gold-soft)] text-[15px] font-bold text-[var(--gold-text)]">
                {{ selected.name[0]?.toUpperCase() }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[15px] font-semibold text-[var(--text-strong)]">{{ selected.name }}</span>
                <span class="block text-[13px] font-normal text-[var(--text-mute)]">{{ selected.phone ?? '' }}</span>
            </span>
            <button
                type="button"
                :aria-label="$t('common.clear')"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                @click="clear"
            >
                <X :size="14" class="text-[var(--text-mute)]" />
            </button>
        </div>
    </template>
</template>
