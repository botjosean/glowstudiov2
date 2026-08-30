<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, Plus, Search, UserPlus, Users } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ClientFormSheet from '../../Components/admin/ClientFormSheet.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    clients: { type: Array, required: true },
    // [{ id, name, phone, tags, upcomingCount }]
});

const search = ref('');

// Client-side: the whole book comes down with the page and a salon's book is
// hundreds of rows at most — a server round-trip per keystroke buys nothing.
const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') return props.clients;

    return props.clients.filter((client) =>
        client.name.toLowerCase().includes(needle)
        || (client.phone ?? '').replace(/\D/g, '').includes(needle.replace(/\D/g, '') || ' '),
    );
});

// "Álvaro" has to land under A, not its own one-name Á group, or the fixed
// A-Z strip below would need an entry for every accented letter that shows
// up in the book instead of the 26 it actually offers.
function baseLetter(name) {
    const letter = (name[0] ?? '#').toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return /[A-Z]/.test(letter) ? letter : '#';
}

// Alphabet groups, Booksy-style: the initial is the scanning anchor.
const grouped = computed(() => {
    const groups = new Map();
    for (const client of filtered.value) {
        const letter = baseLetter(client.name);
        if (!groups.has(letter)) groups.set(letter, []);
        groups.get(letter).push(client);
    }
    return [...groups.entries()].map(([letter, list]) => ({ letter, list }));
});

// The fast-scroll strip along the edge, same idea as any phone's contact
// list: only letters the book actually has are live, tapping or dragging
// across it jumps straight to that group instead of scrolling past 80 names.
const INDEX_LETTERS = [...'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '#'];
const availableLetters = computed(() => new Set(grouped.value.map((group) => group.letter)));
const activeLetter = ref('');
let activeLetterTimer = null;

function flashActiveLetter(letter) {
    activeLetter.value = letter;
    clearTimeout(activeLetterTimer);
    activeLetterTimer = setTimeout(() => {
        activeLetter.value = '';
    }, 600);
}

function jumpToLetter(letter, smooth) {
    if (!availableLetters.value.has(letter)) return;
    flashActiveLetter(letter);
    // Instant while scrubbing (a queued smooth-scroll per letter the finger
    // crosses fights itself); a single deliberate tap gets the easing.
    document.getElementById(`clients-letter-${letter}`)?.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
}

// One pointer stream covers both a tap and a drag scrub: elementFromPoint
// at the current finger position reads whichever letter button is under it,
// same technique iOS/Android use for their own index strips.
const dragging = ref(false);

function letterAtPoint(x, y) {
    return document.elementFromPoint(x, y)?.closest('[data-letter]')?.dataset.letter ?? null;
}

function onIndexPointerDown(event) {
    dragging.value = true;
    event.currentTarget.setPointerCapture(event.pointerId);
    const letter = letterAtPoint(event.clientX, event.clientY);
    if (letter) jumpToLetter(letter, true);
}

function onIndexPointerMove(event) {
    if (!dragging.value) return;
    const letter = letterAtPoint(event.clientX, event.clientY);
    if (letter) jumpToLetter(letter, false);
}

function onIndexPointerUp() {
    dragging.value = false;
}

const createOpen = ref(false);

// La API de contactos del navegador solo la trae Chrome de Android. Antes,
// cuando no estaba, el botón directamente NO SE DIBUJABA — y en iPhone, en
// Brave o en escritorio no quedaba ninguna forma de traer la libreta. Ella lo
// reportó desde el perfil de Paty: «ya no le da la opción como de importar
// todos tus contactos».
//
// Ahora el botón está siempre: con la API se abre el selector del teléfono, y
// sin ella se pide el archivo .vcf/.csv que exporta cualquier teléfono.
const pickerSupported = ref(false);
const importing = ref(false);
const fileInput = ref(null);
const helpOpen = ref(false);

onMounted(() => {
    pickerSupported.value = 'contacts' in navigator && 'select' in navigator.contacts;
});

function importContacts() {
    if (importing.value) return;

    if (pickerSupported.value) {
        pickFromPhone();

        return;
    }

    fileInput.value?.click();
}

async function pickFromPhone() {
    let picked;
    try {
        picked = await navigator.contacts.select(['name', 'tel'], { multiple: true });
    } catch {
        return; // cancelado o denegado — nada que reportar
    }
    const contacts = (picked ?? [])
        .map((contact) => ({
            name: (contact.name?.[0] ?? '').trim(),
            phone: contact.tel?.[0] ?? '',
        }))
        .filter((contact) => contact.name !== '');
    if (contacts.length === 0) return;
    send({ contacts });
}

function pickFile(event) {
    const file = event.target.files?.[0];
    // Se limpia el input para poder volver a elegir el MISMO archivo: sin
    // esto, el segundo intento no dispara nada.
    event.target.value = '';
    if (file) send({ file });
}

function send(payload) {
    importing.value = true;
    router.post('/admin/clientes/importar', payload, {
        forceFormData: payload.file !== undefined,
        preserveScroll: true,
        onFinish: () => {
            importing.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="px-4 pt-4">
            <h1 class="px-1 pb-3 text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.clientsTitle') }}
            </h1>

            <label class="flex items-center gap-2.5 rounded-xl bg-[var(--surface-mute)] px-3.5 py-2.5">
                <Search :size="17" class="shrink-0 text-[var(--text-faint)]" />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('admin.clientsSearch')"
                    class="w-full bg-transparent text-[15px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:outline-none"
                />
            </label>
            <!-- Siempre presente, con API de contactos o sin ella. -->
            <input
                ref="fileInput"
                type="file"
                accept=".vcf,.csv,text/vcard,text/x-vcard,text/csv,text/plain"
                class="hidden"
                @change="pickFile"
            />

            <button
                v-if="clients.length > 0"
                type="button"
                :disabled="importing"
                class="mt-2.5 flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="importContacts"
            >
                <UserPlus :size="15" />
                {{ importing ? $t('common.saving') : (pickerSupported ? $t('admin.importContacts') : $t('admin.importFromFile')) }}
            </button>

            <button
                v-if="clients.length > 0 && !pickerSupported"
                type="button"
                class="mt-2 w-full text-center text-[12px] font-medium text-[var(--text-mute)] underline"
                @click="helpOpen = true"
            >
                {{ $t('admin.importHelp') }}
            </button>
        </div>

        <div v-if="clients.length === 0" class="flex flex-col items-center px-8 pb-6 pt-14 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[var(--surface-mute)]">
                <Users :size="24" class="text-[var(--text-faint)]" />
            </div>
            <p class="mt-4 text-base font-bold text-[var(--text-strong)]">{{ $t('admin.clientsEmpty') }}</p>
            <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.clientsEmptyHint') }}</p>
            <button
                type="button"
                :disabled="importing"
                class="mt-5 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="importContacts"
            >
                {{ importing ? $t('common.saving') : (pickerSupported ? $t('admin.importContacts') : $t('admin.importFromFile')) }}
            </button>

            <button
                v-if="!pickerSupported"
                type="button"
                class="mt-2.5 text-[12px] font-medium text-[var(--text-mute)] underline"
                @click="helpOpen = true"
            >
                {{ $t('admin.importHelp') }}
            </button>
        </div>

        <div v-else class="px-4 pb-4 pt-2 pr-7 min-[700px]:columns-2 min-[700px]:gap-x-6">
            <template v-for="group in grouped" :key="group.letter">
                <div
                    :id="`clients-letter-${group.letter}`"
                    class="bg-[var(--bg-canvas)] px-1 pb-1 pt-4 text-[12px] font-bold text-[var(--text-faint)] min-[700px]:break-inside-avoid"
                >
                    {{ group.letter }}
                </div>
                <Link
                    v-for="client in group.list"
                    :key="client.id"
                    :href="`/admin/clientes/${client.id}`"
                    class="flex items-center gap-3 border-b border-[var(--surface-mute)] px-1 py-3 last:border-b-0 hover:bg-[var(--surface-alt)] min-[700px]:break-inside-avoid"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--surface-mute)] text-[15px] font-bold text-[var(--text-mute)]">
                        {{ client.name[0]?.toUpperCase() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[15px] font-semibold text-[var(--text-strong)]">{{ client.name }}</span>
                        <span class="block truncate text-[13px] font-normal text-[var(--text-mute)]">
                            {{ client.phone ?? '—' }}
                        </span>
                    </span>
                    <span
                        v-if="client.upcomingCount > 0"
                        class="shrink-0 rounded-full bg-[var(--gold-soft)] px-2.5 py-1 text-[11px] font-bold text-[var(--gold-text)]"
                    >
                        {{ client.upcomingCount }}
                    </span>
                    <ChevronRight :size="16" class="shrink-0 text-[var(--text-faint)]" />
                </Link>
            </template>
            <p v-if="filtered.length === 0" class="py-8 text-center text-sm font-medium text-[var(--text-mute)]">
                {{ $t('admin.clientsEmpty') }}
            </p>
        </div>

        <!-- La tira de salto rápido, como en cualquier libreta de contactos:
             tocar o arrastrar el dedo por las letras salta directo a ese
             grupo en vez de desplazarse pasando 80 nombres. -->
        <div
            v-if="clients.length > 0"
            class="fixed right-0.5 top-1/2 z-20 flex max-h-[70vh] -translate-y-1/2 touch-none select-none flex-col items-center justify-center gap-y-px sm:right-[calc(50vw-216px)] min-[700px]:right-[calc(50vw-386px)] lg:right-[calc(50vw-488px)]"
            @pointerdown.prevent="onIndexPointerDown"
            @pointermove="onIndexPointerMove"
            @pointerup="onIndexPointerUp"
            @pointercancel="onIndexPointerUp"
        >
            <button
                v-for="letter in INDEX_LETTERS"
                :key="letter"
                type="button"
                tabindex="-1"
                :data-letter="letter"
                class="flex h-[15px] w-[15px] items-center justify-center text-[9px] font-bold leading-none"
                :class="availableLetters.has(letter) ? 'text-[var(--text-mute)]' : 'text-[var(--text-faint)] opacity-40'"
            >
                {{ letter }}
            </button>
        </div>

        <Transition
            enter-active-class="transition-opacity duration-100"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-300"
            leave-to-class="opacity-0"
        >
            <div
                v-if="activeLetter"
                class="pointer-events-none fixed right-14 top-1/2 z-20 flex h-16 w-16 -translate-y-1/2 items-center justify-center rounded-2xl bg-[var(--btn-bg)]/90 text-[28px] font-bold text-white backdrop-blur-sm"
            >
                {{ activeLetter }}
            </div>
        </Transition>

        <button
            type="button"
            :aria-label="$t('admin.addClient')"
            class="fixed bottom-24 right-4 z-20 flex h-13 w-13 items-center justify-center rounded-full bg-[var(--btn-bg)] shadow-[0_8px_20px_rgba(0,0,0,0.25)] hover:bg-[var(--btn-hover)] sm:right-[calc(50vw-224px)] min-[700px]:right-[calc(50vw-394px)] lg:right-[calc(50vw-496px)]"
            @click="createOpen = true"
        >
            <Plus :size="22" class="text-white" />
        </button>

        <ClientFormSheet v-model="createOpen" />

        <!-- Cómo sacar el .vcf del teléfono, para quien no tiene el selector
             de contactos del navegador. -->
        <BottomSheet v-model="helpOpen">
            <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.importHelp') }}
            </div>
            <p class="mt-2 text-[14px] font-normal leading-relaxed text-[var(--text-body)]">
                {{ $t('admin.importHelpBody') }}
            </p>
            <button
                type="button"
                class="mt-5 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                @click="helpOpen = false; importContacts()"
            >
                {{ $t('admin.importFromFile') }}
            </button>
        </BottomSheet>
    </AdminLayout>
</template>
