<script setup>
import { nextTick, ref, watch } from 'vue';
import { Check, Copy, Download, Share2, X } from '@lucide/vue';
import QRCode from 'qrcode';
import { useI18n } from 'vue-i18n';
import BottomSheet from '../ui/BottomSheet.vue';
import { CROWN_STROKES } from '../../crown';

/**
 * The profile's QR: scan → booking page. Drawn client-side with the highest
 * error-correction level so the brand mark can sit in the middle without
 * eating the code — the same trick Booksy uses with its logo.
 *
 * The center mark is the crown, same strokes as everywhere else. It cannot be
 * the <GlowMark> component: this goes through an <img> into a canvas, so it has
 * to be standalone SVG markup with no external stylesheet. Only the paths are
 * shared, and two things are deliberately NOT:
 *
 * - **The gold.** The brand gradient peaks at a very pale #F5D9BC that would
 *   dissolve into the white badge. These three stops are the light-theme gold
 *   tokens, which is what the sparkle already used and what a QR that gets
 *   printed needs.
 * - **The stroke weight.** The crown renders about 110px wide inside the
 *   downloaded code and a third of that on screen; at that size the 5.5 of the
 *   toolbar mark comes out as a hairline.
 */
const props = defineProps({
    url: { type: String, required: true },
    providerName: { type: String, default: '' },
});

const open = defineModel({ type: Boolean, default: false });

const { t } = useI18n();

const canvasEl = ref(null);

// Three units of slack around CROWN_VIEWBOX: the round caps of the gems sit
// right on that box, and here there is no `overflow: visible` to escape with —
// a rasterised SVG clips at its viewBox and the outer stones would come out
// shaved. The explicit width/height matter too: Safari refuses to rasterise a
// viewBox-only SVG through an <img>.
const MARK_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="21 39 158 92" width="158" height="92">
    <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#e3c26d"/>
            <stop offset="0.5" stop-color="#b3852f"/>
            <stop offset="1" stop-color="#8a6a25"/>
        </linearGradient>
    </defs>
    <g fill="none" stroke="url(#g)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round">
        ${CROWN_STROKES.map((d) => `<path d="${d}"/>`).join('')}
    </g>
</svg>`;

async function draw() {
    await nextTick();
    const canvas = canvasEl.value;
    if (!canvas) return;

    await QRCode.toCanvas(canvas, props.url, {
        width: 640,
        margin: 2,
        errorCorrectionLevel: 'H',
        color: { dark: '#1a1a1a', light: '#ffffff' },
    });

    // The library pins its pixel size as inline style, overriding the
    // classes; the canvas stays 640px for a crisp download but displays small.
    canvas.style.width = '224px';
    canvas.style.height = '224px';

    const ctx = canvas.getContext('2d');
    const size = canvas.width;
    const badge = size * 0.22;
    const corner = (size - badge) / 2;

    // White rounded badge behind the mark so the crown never fights the
    // modules it covers.
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.roundRect(corner, corner, badge, badge, badge * 0.22);
    ctx.fill();

    await new Promise((resolve) => {
        const image = new Image();
        image.onload = () => {
            const mark = badge * 0.78;
            ctx.drawImage(image, (size - mark) / 2, (size - mark) / 2, mark, mark);
            resolve();
        };
        // A failed mark load still leaves a valid QR behind.
        image.onerror = resolve;
        image.src = `data:image/svg+xml;utf8,${encodeURIComponent(MARK_SVG)}`;
    });
}

watch(open, (isOpen) => {
    if (isOpen) draw();
});

function download() {
    const canvas = canvasEl.value;
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = 'qr-reservas.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

const copied = ref(false);

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Non-secure context: the visible URL below stays selectable.
    }
}

async function share() {
    const text = t('admin.shareMessage', { provider: props.providerName, url: props.url });
    if (navigator.share) {
        try {
            await navigator.share({ text });
            return;
        } catch {
            // Cancelled — nothing to do.
            return;
        }
    }
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-4 flex items-center justify-between">
            <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.shareProfile') }}
            </div>
            <button
                type="button"
                :aria-label="$t('common.close')"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                @click="open = false"
            >
                <X :size="16" class="text-[var(--text-mute)]" />
            </button>
        </div>

        <div class="flex flex-col items-center">
            <!-- White frame regardless of theme: a QR lives on white. -->
            <div class="rounded-2xl border border-[var(--surface-mute)] bg-white p-3">
                <canvas ref="canvasEl" class="block h-56 w-56" />
            </div>
            <p class="mt-2 break-all text-center text-[13px] font-semibold text-[var(--text-strong)]">
                {{ url.replace(/^https?:\/\//, '') }}
            </p>
            <p class="mt-2 px-4 text-center text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">
                {{ $t('admin.shareQrHint') }}
            </p>
        </div>

        <div class="mt-5 flex flex-col gap-2">
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                @click="download"
            >
                <Download :size="17" />
                {{ $t('admin.shareQrDownload') }}
            </button>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                    @click="copyLink"
                >
                    <component :is="copied ? Check : Copy" :size="15" />
                    {{ copied ? $t('admin.shareCopied') : $t('admin.shareCopy') }}
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                    @click="share"
                >
                    <Share2 :size="15" />
                    {{ $t('admin.shareSend') }}
                </button>
            </div>
        </div>
    </BottomSheet>
</template>
