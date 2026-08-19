<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { toast } from 'vue-sonner'

const { t } = useI18n();

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const slides = [
    {
        title: 'nextbook',
        highlight: 'helped us collect more than 80,000 leads in a month,',
        text: 'accelerating our search for emails while reducing the cost per lead.',
        name: 'Dmitry Chervonyi',
        role: 'CMO at Belkins',
    },
    {
        title: 'nextbook',
        highlight: 'boosted our sales pipeline in weeks,',
        text: 'giving our team a reliable flow of high-quality prospects.',
        name: 'Sales Team',
        role: 'Nextbook customer',
    },
];

const currentSlide = ref(0);
let slideInterval = null;

const startSlider = () => {
    if (slides.length <= 1) return;

    slideInterval = window.setInterval(() => {
        currentSlide.value = (currentSlide.value + 1) % slides.length;
    }, 6000);
};

const stopSlider = () => {
    if (slideInterval !== null) {
        clearInterval(slideInterval);
        slideInterval = null;
    }
};

onMounted(startSlider);
onBeforeUnmount(stopSlider);

const isLoading = ref(false);

const submit = () => {
    isLoading.value = true;
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => {
            form.reset('password');
            isLoading.value = false;
            toast.success(t('general.success'), {
                description: t('auth.login_success'),
                class: 'bg-green-600 text-white',
            })
        },
        onError: () => {
            toast.error(t('general.error'), {
                description: t('auth.login_error'),
                class: 'bg-red-600 text-white',
            })
        },
    });
};
</script>

<template>
    <Head title="Login" />

    <div class="min-h-screen bg-[#f6f7fb] flex items-center justify-center px-2 py-8">
        <div
            class="relative bg-white rounded-[32px] w-full max-w-6xl flex flex-col md:flex-row overflow-hidden shadow-[0_24px_80px_rgba(15,23,42,0.12)] transition-all"
        >
            <!-- Left side - Nextbook testimonial / slider -->
            <div
                class="w-full md:w-1/2 bg-gradient-to-b from-[#f5e9ff] via-[#fbe8ff] to-[#ffeef6] px-6 py-8 md:px-12 md:py-12 flex flex-col justify-between"
            >
                <!-- Logo / brand -->
                <div>
                    <div class="text-3xl md:text-4xl font-semibold tracking-tight text-[#5531ff] mb-8 md:mb-10 text-center md:text-left">
                        Next<span class="font-normal text-[#ff5ca8]">book</span>
                    </div>
                    <!-- Slider card -->
                    <div
                        class="bg-white rounded-3xl shadow-[0_18px_60px_rgba(15,23,42,0.12)] px-6 py-7 md:px-10 md:py-9 mx-auto md:mx-0 max-w-sm md:max-w-md"
                    >
                        <div class="mb-6">
                            <div class="h-9 w-32 bg-gray-100 rounded-md mb-4 flex items-center justify-center mx-auto md:mx-0">
                                <span class="text-xs font-semibold tracking-wide text-gray-500">
                                    Belkins
                                </span>
                            </div>
                            <p class="text-gray-500 text-sm leading-relaxed text-center md:text-left">
                                <span class="font-semibold text-gray-800">
                                    {{ slides[currentSlide].title }} {{ slides[currentSlide].highlight }}
                                </span>
                                {{ slides[currentSlide].text }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 justify-center md:justify-start">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-tr from-[#f97316] to-[#ec4899]" />
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ slides[currentSlide].name }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ slides[currentSlide].role }}
                                </p>
                            </div>
                        </div>

                        <!-- Slider dots -->
                        <div class="flex items-center gap-2 mt-6 justify-center md:justify-start">
                            <button
                                v-for="(slide, index) in slides"
                                :key="index"
                                type="button"
                                class="h-2 rounded-full transition-all duration-300"
                                :class="index === currentSlide ? 'w-5 bg-[#5531ff]' : 'w-2 bg-gray-300'"
                                @click="currentSlide = index"
                                @mouseenter="stopSlider"
                                @mouseleave="startSlider"
                            />
                        </div>
                    </div>
                </div>

                <!-- Badges placeholders -->
                <div class="mt-8 md:mt-10 flex gap-3 md:gap-6 justify-center md:justify-start">
                    <div
                        class="h-20 w-20 md:h-24 md:w-24 rounded-2xl border border-dashed border-white/70 bg-white/40 backdrop-blur-sm flex items-center justify-center text-[10px] md:text-[11px] font-semibold text-white text-center"
                    >
                        Badge 1
                    </div>
                    <div
                        class="h-20 w-20 md:h-24 md:w-24 rounded-2xl border border-dashed border-white/70 bg-white/40 backdrop-blur-sm flex items-center justify-center text-[10px] md:text-[11px] font-semibold text-white text-center"
                    >
                        Badge 2
                    </div>
                    <div
                        class="h-20 w-20 md:h-24 md:w-24 rounded-2xl border border-dashed border-white/70 bg-white/40 backdrop-blur-sm flex items-center justify-center text-[10px] md:text-[11px] font-semibold text-white text-center"
                    >
                        Badge 3
                    </div>
                </div>
            </div>

            <!-- Right side - Login form -->
            <div class="w-full md:w-1/2 px-4 py-8 md:px-16 md:py-12 flex flex-col">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold text-center text-slate-900 mb-6">
                        {{ t('auth.login') }}
                    </h1>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                                {{ t('auth.email') }}
                            </label>
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="w-full px-3.5 py-3.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#e0ddff] focus:border-[#6c4dff] text-sm placeholder:text-slate-400"
                                placeholder="ahmad@example.com"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                                {{ t('auth.password') }}
                            </label>
                            <div class="relative">
                                <TextInput
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    class="w-full px-3.5 py-3.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#e0ddff] focus:border-[#6c4dff] text-sm placeholder:text-slate-400 pr-10"
                                    placeholder="8+ characters"
                                    required
                                />
                                <span
                                    class="absolute inset-y-0 right-3 flex items-center text-slate-300 pointer-events-none"
                                >
                                    👁
                                </span>
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-2 md:gap-0">
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.remember"
                                    class="w-4 h-4 rounded border-slate-300 text-[#6c4dff] focus:ring-[#6c4dff]"
                                >
                                <span class="text-sm text-slate-600">{{ t('auth.remember_me') }}</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-medium text-[#6c4dff] hover:text-[#5531ff] mt-2 md:mt-0"
                            >
                                {{ t('auth.forgot_password') }}
                            </Link>
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-[#6c4dff] hover:bg-[#5531ff] text-white py-3.5 rounded-xl font-semibold text-[15px] shadow-md shadow-[#6c4dff]/30 transition-colors flex items-center justify-center relative"
                            :disabled="form.processing || isLoading"
                        >
                            <span v-if="isLoading" class="flex items-center justify-center">
                                <span class="mr-2 inline-flex">
                                    <svg
                                        class="animate-spin h-5 w-5 text-gray-700"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <circle
                                            class="opacity-20"
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            d="M21 12a9 9 0 00-9-9"
                                            stroke="currentColor"
                                            stroke-width="4"
                                            stroke-linecap="round"
                                        />
                                    </svg>
                                </span>
                                <span>{{ t('auth.logging_in') }}</span>
                            </span>
                            <span v-else>
                                {{ t('auth.sign_in') }}
                            </span>
                        </button>
                    </form>

                    <p class="mt-6 text-sm text-slate-600 text-center">
                        {{ t('auth.dont_have_an_account') }}
                        <Link
                            :href="route('register')"
                            class="font-semibold text-[#6c4dff] hover:text-[#5531ff] ml-1"
                        >
                            {{ t('auth.sign_up') }}
                        </Link>
                    </p>
                </div>
                <div class="mt-auto pt-10 flex items-center justify-center gap-2 text-sm text-slate-400">
                     <LanguageSwitcher />
                </div>
            </div>

            <!-- Chat bubble placeholder -->
            <button
                type="button"
                class="hidden md:flex h-12 w-12 rounded-full bg-[#6c4dff] text-white shadow-lg shadow-[#6c4dff]/40 items-center justify-center fixed right-10 bottom-10 z-40"
            >
                ?
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Make layout more responsive for mobile */
@media (max-width: 768px) {
    .min-h-screen {
        min-height: 100dvh;
    }
    .rounded-\[32px\] {
        border-radius: 1rem !important;
    }
    .shadow-\[0_24px_80px_rgba\(15\,23\,42\,0\.12\)\] {
        box-shadow: 0 8px 24px rgba(15,23,42,0.12) !important;
    }
}

/* Custom checkbox styles */
input[type='checkbox']:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
}
@keyframes spin {
    100% {
        transform: rotate(360deg);
    }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
