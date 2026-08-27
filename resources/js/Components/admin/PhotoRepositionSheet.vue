<script setup>
/**
 * Entre elegir la foto y subirla: antes, el servidor la recortaba sola con
 * un ancla fija ('top' en el avatar, 'center' en la portada — ver
 * ImageVariant::cropPosition()), así que una selfie de cerca o una foto de
 * cuerpo entero con la cara más abajo salía mal encuadrada sin forma de
 * corregirla. Esto deja arrastrar la foto con el dedo hasta que la cara
 * quede donde debe, y esa posición es la que el servidor usa para recortar
 * de verdad (StoreProviderImage::cropToFocus) — lo que se ve aquí es
 * exactamente lo que queda guardado.
 *
 * El arrastre se mide en un solo eje a la vez: el avatar es cuadrado
 * (512×512) así que casi siempre sobra alto y ancho por igual, pero la
 * portada es ancha (1600×600) y con una foto vertical el sobrante real está
 * casi todo en el eje Y — por eso el cálculo no asume cuál eje tiene
 * espacio, lo mide.
 */
import { computed, nextTick, ref, watch } from 'vue';
import { Check, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    file: { type: File, default: null },
    shape: { type: String, default: 'circle' }, // circle (avatar, 1:1) | banner (1600:600)
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['confirm', 'cancel']);

const { t } = useI18n();

const previewUrl = ref('');
const frameEl = ref(null);
const imgEl = ref(null);

const naturalW = ref(0);
const naturalH = ref(0);
const frameW = ref(0);
const frameH = ref(0);
const offsetX = ref(0);
const offsetY = ref(0);

const renderedScale = computed(() => (naturalW.value && frameW.value
    ? Math.max(frameW.value / naturalW.value, frameH.value / naturalH.value)
    : 1));
const maxOffsetX = computed(() => Math.max(0, naturalW.value * renderedScale.value - frameW.value));
const maxOffsetY = computed(() => Math.max(0, naturalH.value * renderedScale.value - frameH.value));

const posX = computed(() => (maxOffsetX.value === 0 ? 50 : Math.round((offsetX.value / maxOffsetX.value) * 100)));
const posY = computed(() => (maxOffsetY.value === 0 ? 50 : Math.round((offsetY.value / maxOffsetY.value) * 100)));

function recenter() {
    offsetX.value = maxOffsetX.value / 2;
    offsetY.value = maxOffsetY.value / 2;
}

function measureFrame() {
    if (!frameEl.value) return;
    const rect = frameEl.value.getBoundingClientRect();
    frameW.value = rect.width;
    frameH.value = rect.height;
    recenter();
}

function onImgLoad() {
    naturalW.value = imgEl.value.naturalWidth;
    naturalH.value = imgEl.value.naturalHeight;
    nextTick(measureFrame);
}

watch(() => props.file, (file, previousFile) => {
    if (previousFile && previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = file ? URL.createObjectURL(file) : '';
    naturalW.value = 0;
    naturalH.value = 0;
    offsetX.value = 0;
    offsetY.value = 0;
});

const dragging = ref(false);
let startPointerX = 0;
let startPointerY = 0;
let startOffsetX = 0;
let startOffsetY = 0;

function onPointerDown(event) {
    dragging.value = true;
    startPointerX = event.clientX;
    startPointerY = event.clientY;
    startOffsetX = offsetX.value;
    startOffsetY = offsetY.value;
    frameEl.value?.setPointerCapture(event.pointerId);
}

function onPointerMove(event) {
    if (!dragging.value) return;
    // Restar el arrastre: llevar el dedo a la derecha corre la ventana de
    // recorte hacia la izquierda de la foto, como en cualquier editor.
    offsetX.value = Math.min(maxOffsetX.value, Math.max(0, startOffsetX - (event.clientX - startPointerX)));
    offsetY.value = Math.min(maxOffsetY.value, Math.max(0, startOffsetY - (event.clientY - startPointerY)));
}

function onPointerUp() {
    dragging.value = false;
}

function confirm() {
    emit('confirm', { x: posX.value, y: posY.value });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[70] flex flex-col bg-black">
            <div class="flex items-center justify-between px-4 pb-3 pt-[max(1rem,env(safe-area-inset-top))]">
                <button
                    type="button"
                    :aria-label="t('common.cancel')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white"
                    @click="emit('cancel')"
                >
                    <X :size="18" />
                </button>
                <span class="text-[14px] font-semibold text-white">
                    {{ shape === 'circle' ? t('admin.repositionAvatarTitle') : t('admin.repositionBannerTitle') }}
                </span>
                <button
                    type="button"
                    :aria-label="t('common.confirm')"
                    class="flex h-9 items-center justify-center gap-1.5 rounded-full bg-white px-4 text-[13px] font-bold text-black"
                    @click="confirm"
                >
                    <Check :size="15" />
                    {{ t('common.confirm') }}
                </button>
            </div>

            <div class="flex flex-1 items-center justify-center overflow-hidden px-6">
                <div
                    ref="frameEl"
                    class="relative w-full touch-none select-none overflow-hidden bg-[#111]"
                    :class="shape === 'circle' ? 'max-w-[320px] rounded-full' : 'max-w-[480px] rounded-2xl'"
                    :style="{ aspectRatio: shape === 'circle' ? '1 / 1' : '1600 / 600' }"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                >
                    <img
                        v-if="previewUrl"
                        ref="imgEl"
                        :src="previewUrl"
                        alt=""
                        draggable="false"
                        class="absolute inset-0 h-full w-full cursor-grab object-cover active:cursor-grabbing"
                        :style="{ objectPosition: `${posX}% ${posY}%` }"
                        @load="onImgLoad"
                    />
                </div>
            </div>

            <p class="px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-2 text-center text-[13px] font-normal text-white/60">
                {{ t('admin.repositionHint') }}
            </p>
        </div>
    </Teleport>
</template>
