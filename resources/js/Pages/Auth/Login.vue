<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useI18n } from 'vue-i18n';

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

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />

    <div class="min-h-screen bg-[#f6f7fb] flex items-center justify-center px-6 py-10">
        <div class="relative bg-white rounded-[32px] w-full max-w-6xl flex overflow-hidden shadow-[0_24px_80px_rgba(15,23,42,0.12)]">
            <!-- Left side - Nextbook testimonial / slider -->
            <div
                class="w-1/2 bg-gradient-to-b from-[#f5e9ff] via-[#fbe8ff] to-[#ffeef6] px-12 py-12 flex flex-col justify-between"
            >
                <!-- Logo / brand -->
                <div>
                    <div class="text-4xl font-semibold tracking-tight text-[#5531ff] mb-10">
                        Next<span class="font-normal text-[#ff5ca8]">book</span>
                    </div>

                    <!-- Slider card -->
                    <div
                        class="bg-white rounded-3xl shadow-[0_18px_60px_rgba(15,23,42,0.12)] px-10 py-9 max-w-md"
                    >
                        <div class="mb-6">
                            <div class="h-9 w-32 bg-gray-100 rounded-md mb-4 flex items-center justify-center">
                                <span class="text-xs font-semibold tracking-wide text-gray-500">
                                    Belkins
                                </span>
                            </div>

                            <p class="text-gray-500 text-sm leading-relaxed">
                                <span class="font-semibold text-gray-800">
                                    {{ slides[currentSlide].title }} {{ slides[currentSlide].highlight }}
                                </span>
                                {{ slides[currentSlide].text }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
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
                        <div class="flex items-center gap-2 mt-6">
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
                <div class="mt-10 flex gap-6">
                    <div
                        class="h-24 w-24 rounded-2xl border border-dashed border-white/70 bg-white/40 backdrop-blur-sm flex items-center justify-center text-[11px] font-semibold text-white text-center"
                    >
                        Badge 1
                    </div>
                    <div
                        class="h-24 w-24 rounded-2xl border border-dashed border-white/70 bg-white/40 backdrop-blur-sm flex items-center justify-center text-[11px] font-semibold text-white text-center"
                    >
                        Badge 2
                    </div>
                    <div
                        class="h-24 w-24 rounded-2xl border border-dashed border-white/70 bg-white/40 backdrop-blur-sm flex items-center justify-center text-[11px] font-semibold text-white text-center"
                    >
                        Badge 3
                    </div>
                </div>
            </div>

            <!-- Right side - Login form -->
            <div class="w-1/2 px-16 py-12 flex flex-col">
                <div>
                    <h1 class="text-3xl font-semibold text-center text-slate-900 mb-6">
                        {{ t('auth.login') }}
                    </h1>

                    <!-- Google button -->
                    <!-- <button
                        type="button"
                        class="w-full border border-slate-200 rounded-xl py-3.5 mb-6 flex items-center justify-center gap-3 text-[15px] font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                    >
                        <span class="h-5 w-5 rounded-full bg-white shadow flex items-center justify-center">
                            <span class="h-3 w-3 rounded-full bg-[#ea4335]" />
                        </span>
                        <span>Sign in with Google</span>
                    </button> -->

                    <!-- Divider -->
                    <!-- <div class="flex items-center gap-3 mb-6">
                        <span class="flex-1 h-px bg-slate-200" />
                        <span class="text-xs font-medium text-slate-400 uppercase">or</span>
                        <span class="flex-1 h-px bg-slate-200" />
                    </div> -->

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
                                placeholder="johnsmith@example.com"
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

                        <div class="flex items-center justify-between">
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
                                class="text-sm font-medium text-[#6c4dff] hover:text-[#5531ff]"
                            >
                                {{ t('auth.forgot_password') }}
                            </Link>
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-[#6c4dff] hover:bg-[#5531ff] text-white py-3.5 rounded-xl font-semibold text-[15px] shadow-md shadow-[#6c4dff]/30 transition-colors"
                            :disabled="form.processing"
                        >
                            {{ t('auth.sign_in') }}
                        </button>
                    </form>

                    <p class="mt-6 text-sm text-slate-600">
                        {{ t('auth.dont_have_an_account') }}
                        <Link
                            :href="route('register')"
                            class="font-semibold text-[#6c4dff] hover:text-[#5531ff]"
                        >
                            {{ t('auth.sign_up') }}
                        </Link>
                    </p>
                </div> 
            </div>

            <!-- Chat bubble placeholder -->
            <button
                type="button"
                class="hidden md:flex h-12 w-12 rounded-full bg-[#6c4dff] text-white shadow-lg shadow-[#6c4dff]/40 items-center justify-center fixed right-10 bottom-10"
            >
                ?
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Custom checkbox styles */
input[type='checkbox']:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
}
</style>
