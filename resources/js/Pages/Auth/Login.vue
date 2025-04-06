                            <script setup>
                            import { Head, Link, useForm } from '@inertiajs/vue3';
                            import TextInput from '@/Components/TextInput.vue';
                            import InputError from '@/Components/InputError.vue';

                            defineProps({
                                canResetPassword: Boolean,
                                status: String,
                            });

                            const form = useForm({
                                email: '',
                                password: '',
                                remember: false,
                            });

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

                                <div class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
                                    <div class="bg-white rounded-3xl w-full max-w-6xl flex overflow-hidden shadow-xl">
                                        <!-- Left side - Form -->
                                        <div class="w-1/2 p-12">
                                            <div class="mb-2">
                                                <h3 class="text-sm font-medium text-purple-600">nextbook</h3>
                                            </div>

                                            <div class="mb-12">
                                                <h1 class="text-4xl font-bold mb-2"> Welcome Back</h1>
                                                <p class="text-gray-500">Hey, welcome back to your special place</p>
                                            </div>

                                            <form @submit.prevent="submit" class="space-y-6">
                                                <div>
                                                    <TextInput
                                                        id="email"
                                                        v-model="form.email"
                                                        type="email"
                                                        class="w-full p-4 rounded-lg border border-gray-200 focus:ring-2 focus:ring-purple-100 focus:border-purple-300 transition-colors"
                                                        placeholder="stanley@gmail.com"
                                                        required
                                                        autofocus
                                                    />
                                                    <InputError class="mt-2" :message="form.errors.email" />
                                                </div>

                                                <div>
                                                    <TextInput
                                                        id="password"
                                                        v-model="form.password"
                                                        type="password"
                                                        class="w-full p-4 rounded-lg border border-gray-200 focus:ring-2 focus:ring-purple-100 focus:border-purple-300 transition-colors"
                                                        placeholder="••••••••••••"
                                                        required
                                                    />
                                                    <InputError class="mt-2" :message="form.errors.password" />
                                                </div>

                                                <div class="flex items-center justify-between">
                                                    <label class="flex items-center">
                                                        <input
                                                            type="checkbox"
                                                            v-model="form.remember"
                                                            class="w-4 h-4 rounded-sm border-gray-300 text-purple-600 focus:ring-purple-500 checked:bg-purple-600"
                                                        >
                                                        <span class="ml-2 text-sm text-gray-500">Remember me</span>
                                                    </label>

                                                    <Link
                                                        v-if="canResetPassword"
                                                        :href="route('password.request')"
                                                        class="text-sm text-gray-500 hover:text-gray-700"
                                                    >
                                                        Forgot Password?
                                                    </Link>
                                                </div>

                                                <button
                                                    type="submit"
                                                    class="w-40 bg-purple-600 text-white py-3 rounded-xl font-medium hover:bg-purple-700 transition-colors"
                                                    :disabled="form.processing"
                                                >
                                                    Sign In
                                                </button>

                                                <p class="text-sm text-gray-500">
                                                    Don't have an account?
                                                    <Link :href="route('register')" class="text-purple-600 font-medium hover:text-purple-700">
                                                        Sign Up
                                                    </Link>
                                                </p>
                                            </form>
                                        </div>

                                        <!-- Right side - Illustration -->
                                        <div class="w-1/2 bg-illustration"></div>
                                    </div>
                                </div>
                            </template>

                            <style scoped>
                            .bg-illustration {
                                background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
                                position: relative;
                            }

                            .bg-illustration::before {
                                content: '';
                                position: absolute;
                                top: 0;
                                right: 0;
                                bottom: 0;
                                left: 0;
                                background: url('/images/login-image.png') no-repeat center center;
                                background-size: cover;
                            }

                            /* Custom checkbox styles */
                            input[type="checkbox"]:checked {
                                background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
                            }
                            </style>
