<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail, ChevronDown } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import AuthTabs from '../../Components/ui/AuthTabs.vue';
import OutlinedInput from '../../Components/ui/OutlinedInput.vue';
import PasswordField from '../../Components/ui/PasswordField.vue';
import Button from '../../Components/ui/Button.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';
import OptionPicker from '../../Components/ui/OptionPicker.vue';

const { t } = useI18n();

const form = useForm({
    username: '',
    fullName: '',
    phone: '',
    email: '',
    password: '',
    confirmPassword: '',
    // Obligatorio desde el registro: sin rubro, el Taller de Contenido arma
    // los posts con la paleta y los hashtags del oficio equivocado.
    businessCategory: '',
});

// Mismo orden y mismas agrupaciones que el selector de Negocio.vue.
const categoryOptions = computed(() => {
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

const categoryLabel = computed(() => (form.businessCategory
    ? t(`admin.businessCategory_${form.businessCategory}`)
    : ''));

const categoryOpen = ref(false);

const passwordsMatch = computed(
    () => form.confirmPassword.length > 0 && form.confirmPassword === form.password,
);

const firstError = computed(() => Object.values(form.errors)[0] ?? null);

function formatUsPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 10);
    if (digits.length === 0) return '';
    if (digits.length < 4) return `(${digits}`;
    if (digits.length < 7) return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
}

function onPhoneInput(event) {
    form.phone = formatUsPhone(event.target.value);
}

// Booksy's own shape: one field and Google both in view without scrolling,
// the rest of the form (which nobody reads before deciding to sign up at
// all) only shows up once she has actually committed to email.  Still one
// real submit at the end — /register keeps expecting every field at once,
// so this is a display step, not a wizard with its own request.
const step = ref(1);
const emailFieldError = ref('');
const usernameFieldEl = ref(null);

function continueWithEmail() {
    const email = form.email.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        emailFieldError.value = t('signUp.emailInvalid');
        return;
    }
    emailFieldError.value = '';
    step.value = 2;
    nextTick(() => usernameFieldEl.value?.focus());
}

function submit() {
    form.post('/register');
}
</script>

<template>
    <PublicLayout>
        <div class="p-6">
            <AuthTabs active="signup" />

            <template v-if="step === 1">
                <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('signUp.title') }}
                </h1>
                <div class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">{{ $t('signUp.subtitle') }}</div>

                <div class="mt-5 flex flex-col gap-3.5">
                    <OutlinedInput
                        id="signup-email"
                        v-model="form.email"
                        :label="$t('signUp.emailLabel')"
                        type="email"
                        :error="emailFieldError"
                        @keyup.enter="continueWithEmail"
                    />
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2.5 rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                        @click="continueWithEmail"
                    >
                        <Mail :size="17" />
                        {{ $t('common.continueWithEmail') }}
                    </button>
                </div>

                <div class="mt-5 flex items-center gap-2.5">
                    <div class="h-px flex-1 bg-[var(--border-strong)]" />
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-faint)]">{{ $t('signUp.orContinueWith') }}</span>
                    <div class="h-px flex-1 bg-[var(--border-strong)]" />
                </div>

                <a
                    href="/auth/google/redirect"
                    class="mt-3.5 flex w-full items-center justify-center gap-2.5 rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface)] py-3.5 text-[15px] font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-mute)]"
                >
                    <svg width="18" height="18" viewBox="0 0 48 48">
                        <path
                            fill="#EA4335"
                            d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                        />
                        <path
                            fill="#4285F4"
                            d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.9-2.26 5.36-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                        />
                        <path
                            fill="#FBBC05"
                            d="M10.53 28.59A14.5 14.5 0 0 1 9.5 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.94 23.94 0 0 0 0 24c0 3.86.92 7.51 2.56 10.78z"
                        />
                        <path
                            fill="#34A853"
                            d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.9l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                        />
                    </svg>
                    {{ $t('common.continueWithGoogle') }}
                </a>
            </template>

            <template v-else>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :aria-label="$t('common.cancel')"
                        class="-ml-1.5 flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                        @click="step = 1"
                    >
                        <ArrowLeft :size="19" class="text-[var(--text-strong)]" />
                    </button>
                    <div>
                        <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                            {{ $t('signUp.title') }}
                        </div>
                        <div class="text-[13px] font-normal text-[var(--text-mute)]">{{ form.email }}</div>
                    </div>
                </div>

                <form class="mt-5 flex flex-col gap-3.5" @submit.prevent="submit">
                    <p v-if="firstError" class="text-[13px] font-normal text-[var(--danger)]">{{ firstError }}</p>
                    <OutlinedInput id="signup-username" ref="usernameFieldEl" v-model="form.username" :label="$t('signUp.usernameLabel')" type="text" />
                    <OutlinedInput id="signup-fullname" v-model="form.fullName" :label="$t('signUp.fullNameLabel')" type="text" />

                    <!-- El rubro se pregunta acá y no en la guía: de él
                         dependen los posts del Taller de Contenido, y una
                         cuenta sin rubro los arma con el oficio equivocado. -->
                    <button
                        id="signup-category"
                        type="button"
                        class="relative flex w-full items-center rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-[15px] pr-10 text-left text-[15px] font-semibold text-[var(--text-strong)] focus:border-[var(--text-strong)] focus-visible:outline-none"
                        @click="categoryOpen = true"
                    >
                        <span class="truncate" :class="!categoryLabel && 'font-medium text-[var(--text-faint)]'">
                            {{ categoryLabel || $t('signUp.categoryLabel') }}
                        </span>
                        <ChevronDown :size="16" class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]" />
                    </button>

                    <label class="flex flex-col gap-2">
                        <span class="text-[13px] font-medium text-[var(--text-mute)]">{{
                            $t('signUp.phoneLabel')
                        }}</span>
                        <div
                            class="flex items-center gap-2.5 rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-[15px] focus-within:border-[var(--text-strong)]"
                        >
                            <span
                                class="flex shrink-0 items-center gap-1.5 border-r border-[var(--border-strong)] pr-2.5 text-sm font-semibold text-[var(--text-strong)]"
                            >
                                <span class="text-base leading-none">🇺🇸</span>
                                +1
                            </span>
                            <input
                                :value="form.phone"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel-national"
                                :placeholder="$t('signUp.phonePlaceholder')"
                                class="w-full bg-transparent text-sm font-semibold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                                @input="onPhoneInput"
                            />
                        </div>
                    </label>

                    <PasswordField v-model="form.password" :label="$t('signUp.passwordLabel')" />

                    <PasswordField
                        v-model="form.confirmPassword"
                        :label="$t('signUp.confirmPasswordLabel')"
                        :matched="passwordsMatch"
                    />
                </form>

                <div class="mt-5 flex gap-3">
                    <Link
                        href="/"
                        class="flex-1 rounded-xl border-[1.5px] border-[var(--border-strong)] py-3.5 text-center text-[15px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)]"
                        >{{ $t('common.cancel') }}</Link
                    >
                    <Button variant="primary" :disabled="form.processing" class="flex-1" @click="submit">{{
                        $t('menu.createAccount')
                    }}</Button>
                </div>
            </template>
        </div>

        <BottomSheet v-model="categoryOpen">
            <OptionPicker
                v-model="form.businessCategory"
                :options="categoryOptions"
                :title="$t('signUp.categoryLabel')"
                @close="categoryOpen = false"
            />
        </BottomSheet>
    </PublicLayout>
</template>
