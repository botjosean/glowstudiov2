<script setup>
/**
 * Donde una clienta deja sus estrellas.
 *
 * Llega de un enlace de WhatsApp, sin cuenta y sin contraseña, probablemente
 * en la calle y con una mano. Por eso: las estrellas son lo primero y son
 * grandes, el comentario es opcional y está debajo, y no hay nada más en la
 * pantalla. Cada campo de más es gente que no contesta.
 */
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

const props = defineProps({
    provider: { type: Object, required: true }, // { slug, name }
    clientName: { type: String, default: '' },
    alreadyAnswered: { type: Boolean, default: false },
    rating: { type: Number, default: 0 },
});

const form = useForm({ rating: 0, comment: '' });

// El brillo al pasar por encima, para que se entienda que son botones y no
// un dibujo. En un teléfono no hay hover, y ahí manda `form.rating`.
const hovered = ref(0);

function enviar() {
    if (form.rating === 0) return;
    form.post(`/resena/${window.location.pathname.split('/').pop()}`, { preserveScroll: true });
}
</script>

<template>
    <PublicLayout no-splash>
        <div class="mx-auto flex min-h-screen max-w-[480px] flex-col justify-center px-6 py-12">
            <!-- Ya contestada: un gracias, no un error. Tocar dos veces el
                 mismo enlace de WhatsApp es lo más normal del mundo. -->
            <template v-if="alreadyAnswered">
                <div class="text-center">
                    <div class="mb-4 flex justify-center gap-1">
                        <Star
                            v-for="n in 5"
                            :key="n"
                            :size="28"
                            :class="n <= rating ? 'fill-[var(--gold)] text-[var(--gold)]' : 'text-[var(--text-faint)]'"
                        />
                    </div>
                    <h1 class="text-[24px] font-bold text-[var(--text-strong)]">{{ $t('profile.reviewThanksTitle') }}</h1>
                    <p class="mt-2 text-[15px] text-[var(--text-mute)]">
                        {{ $t('profile.reviewThanksBody', { name: provider.name }) }}
                    </p>
                    <Link
                        :href="`/${provider.slug}`"
                        class="mt-8 inline-block rounded-xl bg-[var(--btn-bg)] px-6 py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                    >
                        {{ $t('profile.reviewSeeProfile') }}
                    </Link>
                </div>
            </template>

            <template v-else>
                <h1 class="text-center text-[24px] font-bold leading-tight text-[var(--text-strong)]">
                    {{ $t('profile.reviewTitle', { name: provider.name }) }}
                </h1>
                <p class="mt-2 text-center text-[15px] text-[var(--text-mute)]">
                    {{ $t('profile.reviewSubtitle') }}
                </p>

                <!-- Botones de verdad, no un dibujo con un click encima: se
                     llega con el teclado y se anuncian solos. -->
                <div class="mt-8 flex justify-center gap-2" @mouseleave="hovered = 0">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        class="rounded-lg p-1.5 transition-transform hover:scale-110"
                        :aria-label="$t('profile.reviewStars', n)"
                        :aria-pressed="form.rating === n"
                        @mouseenter="hovered = n"
                        @click="form.rating = n"
                    >
                        <Star
                            :size="40"
                            :class="n <= (hovered || form.rating)
                                ? 'fill-[var(--gold)] text-[var(--gold)]'
                                : 'text-[var(--text-faint)]'"
                        />
                    </button>
                </div>
                <p v-if="form.errors.rating" class="mt-2 text-center text-[13px] font-medium text-[var(--danger)]">
                    {{ form.errors.rating }}
                </p>

                <label class="mt-8 block text-[13px] font-semibold text-[var(--text-heading)]">
                    {{ $t('profile.reviewCommentLabel') }}
                </label>
                <textarea
                    v-model="form.comment"
                    rows="4"
                    maxlength="1000"
                    :placeholder="$t('profile.reviewCommentPlaceholder')"
                    class="mt-1.5 w-full rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-3.5 py-3 text-[15px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)]"
                />
                <p v-if="form.errors.comment" class="mt-1 text-[13px] font-medium text-[var(--danger)]">
                    {{ form.errors.comment }}
                </p>

                <button
                    type="button"
                    :disabled="form.rating === 0 || form.processing"
                    class="mt-6 w-full rounded-xl bg-[var(--btn-bg)] py-4 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-50"
                    @click="enviar"
                >
                    {{ $t('profile.reviewSend') }}
                </button>
            </template>
        </div>
    </PublicLayout>
</template>
