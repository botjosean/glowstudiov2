<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { Copy, Check, ExternalLink } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    url: { type: String, required: true },
    published: { type: Boolean, default: false },
});

const { t } = useI18n();

/** The protocol is noise on a link meant to be read aloud and recognised. */
const displayUrl = computed(() => props.url.replace(/^https?:\/\//, ''));

const shareUrl = computed(
    () => `https://wa.me/?text=${encodeURIComponent(t('admin.publicLinkShareText', { url: props.url }))}`,
);

const copied = ref(false);
const urlEl = ref(null);
let copiedTimer = null;

/**
 * Falls back to selecting the text when the Clipboard API refuses — it needs a
 * secure context, and a button that silently does nothing is worse than one
 * that hands the provider something they can copy themselves.
 */
async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.url);
    } catch {
        selectUrl();

        return;
    }

    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function selectUrl() {
    if (!urlEl.value) {
        return;
    }

    const range = document.createRange();
    range.selectNodeContents(urlEl.value);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

onBeforeUnmount(() => clearTimeout(copiedTimer));
</script>

<template>
    <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div class="flex items-center justify-between gap-2">
            <span class="text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.publicLinkTitle') }}</span>
            <span
                v-if="!published"
                class="shrink-0 rounded-full bg-[var(--surface-mute)] px-2 py-0.5 text-[11px] font-medium text-[var(--text-mute)]"
            >
                {{ $t('admin.publicLinkHidden') }}
            </span>
        </div>

        <div ref="urlEl" class="mt-1.5 break-all text-[17px] font-bold leading-snug text-[var(--text-strong)]">
            {{ displayUrl }}
        </div>

        <p v-if="!published" class="mt-1.5 text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">
            {{ $t('admin.publicLinkHiddenHint') }}
        </p>

        <!--
            Sharing and previewing are hidden until the profile is published:
            PublicController aborts with a 404 while published_at is null, so
            these buttons would hand a client a dead link. Copy stays — saving
            the address for later costs nothing.
        -->
        <div class="mt-3 flex gap-2">
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                @click="copyLink"
            >
                <component :is="copied ? Check : Copy" :size="15" />
                {{ copied ? $t('admin.publicLinkCopied') : $t('admin.publicLinkCopy') }}
            </button>
            <a
                v-if="published"
                :href="shareUrl"
                target="_blank"
                rel="noopener"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[#25D366] py-3 text-[14px] font-semibold text-white hover:brightness-95"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">
                    <path
                        d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.2h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.86 9.86 0 0 0 12.05 2m0 1.67a8.2 8.2 0 0 1 5.83 2.42 8.19 8.19 0 0 1 2.41 5.83c0 4.55-3.7 8.24-8.25 8.24a8.3 8.3 0 0 1-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.4c0-4.55 3.7-8.23 8.26-8.23m-4.57 4.7c-.16 0-.42.06-.64.3-.22.24-.85.83-.85 2.02s.87 2.35.99 2.51c.12.16 1.7 2.69 4.19 3.63 2.07.79 2.49.63 2.94.59.45-.04 1.45-.59 1.65-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28-.24-.12-1.45-.72-1.68-.8-.22-.08-.39-.12-.55.12-.16.24-.63.8-.77.96-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.55-1.36-.77-1.86-.2-.48-.4-.42-.55-.42h-.47Z"
                    />
                </svg>
                {{ $t('admin.publicLinkShare') }}
            </a>
        </div>

        <a
            v-if="published"
            :href="url"
            target="_blank"
            rel="noopener"
            class="mt-2 flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-[13px] font-medium text-[var(--text-mute)] hover:bg-[var(--surface-alt)]"
        >
            {{ $t('admin.publicLinkView') }}
            <ExternalLink :size="13" />
        </a>
    </div>
</template>
