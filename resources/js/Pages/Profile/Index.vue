<script setup>
import { ref, computed } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { Badge } from '@/Components/ui/badge'
import { Separator } from '@/Components/ui/separator'
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog'
import {
    User, Lock, Shield, Monitor, Trash2,
    CheckCircle2, AlertTriangle, Laptop, Smartphone,
    Clock, MapPin, Eye, EyeOff,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
})

const { t } = useI18n()
const page = usePage()
const user = computed(() => page.props.auth.user)

// ─── Section Nav ─────────────────────────────────────────────────────────────
const activeSection = ref('profile')
const sections = computed(() => [
    { id: 'profile',  label: t('profile.sections.profile'),  icon: User    },
    { id: 'password', label: t('profile.sections.password'),  icon: Lock    },
    { id: 'tfa',      label: t('profile.sections.tfa'),       icon: Shield  },
    { id: 'sessions', label: t('profile.sections.sessions'),  icon: Monitor },
    { id: 'danger',   label: t('profile.sections.danger'),    icon: Trash2  },
])

// ─── Profile Info Form ────────────────────────────────────────────────────────
const photoInput   = ref(null)
const photoPreview = ref(null)

const profileForm = useForm({
    _method: 'PUT',
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    photo: null,
})

function onPhotoChange() {
    const file = photoInput.value?.files?.[0]
    if (!file) return
    profileForm.photo = file
    const reader = new FileReader()
    reader.onload = (e) => { photoPreview.value = e.target.result }
    reader.readAsDataURL(file)
}

function removePhoto() {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => { photoPreview.value = null },
    })
}

function saveProfile() {
    profileForm.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('profile.profile.save'))
            photoPreview.value = null
            if (photoInput.value) photoInput.value.value = null
        },
    })
}

// ─── Password Form ────────────────────────────────────────────────────────────
const showCurrentPassword = ref(false)
const showNewPassword      = ref(false)
const showConfirmPassword  = ref(false)

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
})

function savePassword() {
    passwordForm.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('profile.password.save'))
            passwordForm.reset()
        },
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation')
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password')
            }
        },
    })
}

// ─── Two-Factor Auth ──────────────────────────────────────────────────────────
const twoFactorEnabled = computed(() => !! user.value?.two_factor_confirmed_at)
const twoFactorForm    = useForm({})

function enableTwoFactor() {
    twoFactorForm.post(route('two-factor.enable'), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('profile.tfa.enable')),
    })
}

function disableTwoFactor() {
    twoFactorForm.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('profile.tfa.disable')),
    })
}

// ─── Sessions ─────────────────────────────────────────────────────────────────
const logoutPassword    = ref('')
const logoutSessionsForm = useForm({ password: '' })

function logoutOtherSessions() {
    logoutSessionsForm.password = logoutPassword.value
    logoutSessionsForm.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('profile.sessions.logout_btn'))
            logoutPassword.value = ''
        },
    })
}

// ─── Delete Account ───────────────────────────────────────────────────────────
const deletePassword = ref('')
const deleteForm     = useForm({ password: '' })

function deleteAccount() {
    deleteForm.password = deletePassword.value
    deleteForm.delete(route('current-user.destroy'), { preserveScroll: true })
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function initials(name) {
    return (name ?? '')
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
}

function roleLabel(roles) {
    if (!roles || !roles.length) return null
    return roles[0].charAt(0).toUpperCase() + roles[0].slice(1).replace(/_/g, ' ')
}
</script>

<template>
    <AppLayout :title="t('profile.title')">
        <div class="space-y-6">

            <!-- ── Profile Hero ── -->
            <Card class="overflow-hidden">
                <div class="h-24 bg-gradient-to-r from-violet-600 via-purple-500 to-indigo-500" />
                <CardContent class="relative pb-6 pt-0 px-6">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:gap-5 -mt-10">
                        <div class="relative shrink-0">
                            <Avatar class="size-20 ring-4 ring-background shadow-lg">
                                <AvatarImage v-if="photoPreview" :src="photoPreview" />
                                <AvatarImage v-else-if="user.profile_photo_url" :src="user.profile_photo_url" />
                                <AvatarFallback class="text-xl bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300">
                                    {{ initials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                        <div class="flex-1 mt-3 sm:mt-0 sm:mb-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-semibold text-foreground">{{ user.name }}</h1>
                                <Badge v-if="roleLabel(user.roles)" variant="secondary" class="text-xs capitalize">
                                    {{ roleLabel(user.roles) }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground mt-0.5">{{ user.email }}</p>
                            <p v-if="user.branch_name" class="text-xs text-muted-foreground mt-0.5">
                                {{ user.branch_name }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Two-column layout ── -->
            <div class="flex flex-col sm:flex-row gap-6">

                <!-- Left nav -->
                <aside class="sm:w-52 shrink-0">
                    <nav class="flex flex-row sm:flex-col gap-1 overflow-x-auto sm:overflow-visible">
                        <button
                            v-for="s in sections"
                            :key="s.id"
                            @click="activeSection = s.id"
                            :class="[
                                'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors whitespace-nowrap w-full text-start',
                                activeSection === s.id
                                    ? 'bg-violet-600 text-white shadow-sm'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                s.id === 'danger' && activeSection !== 'danger'
                                    ? 'hover:bg-destructive/10 hover:text-destructive'
                                    : '',
                                s.id === 'danger' && activeSection === 'danger'
                                    ? '!bg-destructive text-white'
                                    : '',
                            ]"
                        >
                            <component :is="s.icon" class="size-4 shrink-0" />
                            <span>{{ s.label }}</span>
                        </button>
                    </nav>
                </aside>

                <!-- Right content -->
                <div class="flex-1 min-w-0 space-y-5">

                    <!-- ════ Profile Info ════ -->
                    <Card v-show="activeSection === 'profile'">
                        <CardHeader>
                            <CardTitle>{{ t('profile.profile.title') }}</CardTitle>
                            <CardDescription>{{ t('profile.profile.subtitle') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">

                            <!-- Avatar — GitLab style -->
                            <div>
                                <p class="text-sm font-semibold text-foreground mb-0.5">{{ t('profile.profile.avatar.title') }}</p>
                                <p class="text-sm text-muted-foreground mb-4">{{ t('profile.profile.avatar.subtitle') }}</p>

                                <div class="flex items-start gap-5">
                                    <Avatar class="size-20 shrink-0 ring-2 ring-border">
                                        <AvatarImage v-if="photoPreview" :src="photoPreview" />
                                        <AvatarImage v-else-if="user.profile_photo_url" :src="user.profile_photo_url" />
                                        <AvatarFallback class="text-xl bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300">
                                            {{ initials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div class="flex-1 space-y-2">
                                        <p class="text-sm font-medium text-foreground">{{ t('profile.profile.avatar.upload_label') }}</p>
                                        <input
                                            ref="photoInput"
                                            type="file"
                                            accept="image/*"
                                            class="block text-sm text-muted-foreground
                                                file:mr-3 file:py-1.5 file:px-3
                                                file:rounded-md file:border file:border-border
                                                file:text-sm file:font-medium
                                                file:bg-background file:text-foreground
                                                hover:file:bg-accent hover:file:cursor-pointer
                                                cursor-pointer"
                                            @change="onPhotoChange"
                                        />
                                        <p class="text-xs text-muted-foreground">{{ t('profile.profile.avatar.hint') }}</p>
                                        <Button
                                            v-if="user.profile_photo_path || photoPreview"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="border-destructive text-destructive hover:bg-destructive/10"
                                            @click="removePhoto"
                                        >
                                            {{ t('profile.profile.avatar.remove') }}
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="profileForm.errors.photo" class="text-sm text-destructive mt-2">{{ profileForm.errors.photo }}</p>
                            </div>

                            <Separator />

                            <!-- Name -->
                            <div class="space-y-1.5">
                                <Label for="name">{{ t('profile.profile.name') }}</Label>
                                <Input id="name" v-model="profileForm.name" type="text" autocomplete="name" />
                                <p v-if="profileForm.errors.name" class="text-sm text-destructive">{{ profileForm.errors.name }}</p>
                            </div>

                            <!-- Email -->
                            <div class="space-y-1.5">
                                <Label for="email">{{ t('profile.profile.email') }}</Label>
                                <Input id="email" v-model="profileForm.email" type="email" autocomplete="email" />
                                <p v-if="profileForm.errors.email" class="text-sm text-destructive">{{ profileForm.errors.email }}</p>
                                <div v-if="$page.props.jetstream?.hasEmailVerification && !user.email_verified_at"
                                     class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400 mt-1.5">
                                    <AlertTriangle class="size-4 shrink-0" />
                                    <span>{{ t('profile.profile.email_not_verified') }}
                                        <Link :href="route('verification.send')" method="post" as="button" class="underline font-medium">
                                            {{ t('profile.profile.resend_verification') }}
                                        </Link>
                                    </span>
                                </div>
                                <div v-else class="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                                    <CheckCircle2 class="size-3.5" /> {{ t('profile.profile.verified') }}
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <Button @click="saveProfile" :disabled="profileForm.processing">
                                    {{ profileForm.processing ? t('profile.profile.saving') : t('profile.profile.save') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ════ Password ════ -->
                    <Card v-show="activeSection === 'password'">
                        <CardHeader>
                            <CardTitle>{{ t('profile.password.title') }}</CardTitle>
                            <CardDescription>{{ t('profile.password.subtitle') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Current Password -->
                            <div class="space-y-1.5">
                                <Label for="current_password">{{ t('profile.password.current') }}</Label>
                                <div class="relative">
                                    <Input
                                        id="current_password"
                                        v-model="passwordForm.current_password"
                                        :type="showCurrentPassword ? 'text' : 'password'"
                                        autocomplete="current-password"
                                        class="pe-10"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 end-0 flex items-center px-3 text-muted-foreground"
                                        @click="showCurrentPassword = !showCurrentPassword"
                                    >
                                        <Eye v-if="!showCurrentPassword" class="size-4" />
                                        <EyeOff v-else class="size-4" />
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.current_password" class="text-sm text-destructive">{{ passwordForm.errors.current_password }}</p>
                            </div>

                            <!-- New Password -->
                            <div class="space-y-1.5">
                                <Label for="new_password">{{ t('profile.password.new') }}</Label>
                                <div class="relative">
                                    <Input
                                        id="new_password"
                                        v-model="passwordForm.password"
                                        :type="showNewPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        class="pe-10"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 end-0 flex items-center px-3 text-muted-foreground"
                                        @click="showNewPassword = !showNewPassword"
                                    >
                                        <Eye v-if="!showNewPassword" class="size-4" />
                                        <EyeOff v-else class="size-4" />
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.password" class="text-sm text-destructive">{{ passwordForm.errors.password }}</p>
                            </div>

                            <!-- Confirm Password -->
                            <div class="space-y-1.5">
                                <Label for="password_confirmation">{{ t('profile.password.confirm') }}</Label>
                                <div class="relative">
                                    <Input
                                        id="password_confirmation"
                                        v-model="passwordForm.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        class="pe-10"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 end-0 flex items-center px-3 text-muted-foreground"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                    >
                                        <Eye v-if="!showConfirmPassword" class="size-4" />
                                        <EyeOff v-else class="size-4" />
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.password_confirmation" class="text-sm text-destructive">{{ passwordForm.errors.password_confirmation }}</p>
                            </div>

                            <div class="flex justify-end pt-2">
                                <Button @click="savePassword" :disabled="passwordForm.processing">
                                    {{ passwordForm.processing ? t('profile.password.saving') : t('profile.password.save') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ════ Two-Factor Auth ════ -->
                    <Card v-show="activeSection === 'tfa'">
                        <CardHeader>
                            <CardTitle>{{ t('profile.tfa.title') }}</CardTitle>
                            <CardDescription>{{ t('profile.tfa.subtitle') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="flex items-start gap-4 p-4 rounded-lg border"
                                :class="twoFactorEnabled
                                    ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30'
                                    : 'border-border bg-muted/30'"
                            >
                                <Shield class="size-8 mt-0.5 shrink-0"
                                    :class="twoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'" />
                                <div class="flex-1">
                                    <p class="font-medium text-sm"
                                       :class="twoFactorEnabled ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground'">
                                        {{ twoFactorEnabled ? t('profile.tfa.status_enabled') : t('profile.tfa.status_disabled') }}
                                    </p>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        {{ twoFactorEnabled ? t('profile.tfa.desc_enabled') : t('profile.tfa.desc_disabled') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex justify-end mt-4">
                                <Button v-if="!twoFactorEnabled" @click="enableTwoFactor" :disabled="twoFactorForm.processing">
                                    {{ t('profile.tfa.enable') }}
                                </Button>
                                <Button v-else variant="destructive" @click="disableTwoFactor" :disabled="twoFactorForm.processing">
                                    {{ t('profile.tfa.disable') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ════ Active Sessions ════ -->
                    <Card v-show="activeSection === 'sessions'">
                        <CardHeader>
                            <CardTitle>{{ t('profile.sessions.title') }}</CardTitle>
                            <CardDescription>{{ t('profile.sessions.subtitle') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div v-if="sessions && sessions.length" class="space-y-3">
                                <div
                                    v-for="(session, i) in sessions"
                                    :key="i"
                                    class="flex items-center gap-4 p-3 rounded-lg border bg-muted/30"
                                >
                                    <div class="shrink-0 text-muted-foreground">
                                        <Laptop v-if="session.agent.is_desktop" class="size-8" />
                                        <Smartphone v-else class="size-8" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium">
                                                {{ session.agent.platform }} — {{ session.agent.browser }}
                                            </p>
                                            <Badge v-if="session.is_current_device" class="text-xs" variant="secondary">
                                                {{ t('profile.sessions.this_device') }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-3 mt-0.5 text-xs text-muted-foreground">
                                            <span class="flex items-center gap-1">
                                                <MapPin class="size-3" /> {{ session.ip_address }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Clock class="size-3" /> {{ session.last_active }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-sm text-muted-foreground text-center py-6">
                                {{ t('profile.sessions.no_sessions') }}
                            </div>

                            <Separator />
                            <div class="space-y-3">
                                <p class="text-sm text-muted-foreground">{{ t('profile.sessions.logout_hint') }}</p>
                                <div class="space-y-1.5">
                                    <Label for="logout-password">{{ t('profile.sessions.password') }}</Label>
                                    <Input
                                        id="logout-password"
                                        v-model="logoutPassword"
                                        type="password"
                                        :placeholder="t('profile.sessions.password_placeholder')"
                                    />
                                </div>
                                <div class="flex justify-end">
                                    <Button
                                        variant="outline"
                                        @click="logoutOtherSessions"
                                        :disabled="logoutSessionsForm.processing || !logoutPassword"
                                    >
                                        {{ t('profile.sessions.logout_btn') }}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ════ Delete Account ════ -->
                    <Card v-show="activeSection === 'danger'" class="border-destructive/40">
                        <CardHeader>
                            <CardTitle class="text-destructive">{{ t('profile.danger.title') }}</CardTitle>
                            <CardDescription>{{ t('profile.danger.subtitle') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-start gap-3 p-4 rounded-lg bg-destructive/10 border border-destructive/20 mb-5">
                                <AlertTriangle class="size-5 text-destructive shrink-0 mt-0.5" />
                                <p class="text-sm text-destructive">{{ t('profile.danger.warning') }}</p>
                            </div>

                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <Button variant="destructive">
                                        <Trash2 class="size-4 me-2" />
                                        {{ t('profile.danger.btn') }}
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>{{ t('profile.danger.dialog_title') }}</AlertDialogTitle>
                                        <AlertDialogDescription>{{ t('profile.danger.dialog_desc') }}</AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <div class="space-y-1.5 py-2">
                                        <Label for="delete-password">{{ t('profile.danger.confirm_password') }}</Label>
                                        <Input
                                            id="delete-password"
                                            v-model="deletePassword"
                                            type="password"
                                            :placeholder="t('profile.danger.confirm_placeholder')"
                                        />
                                        <p v-if="deleteForm.errors.password" class="text-sm text-destructive">{{ deleteForm.errors.password }}</p>
                                    </div>
                                    <AlertDialogFooter>
                                        <Button variant="outline" @click="deletePassword = ''">{{ t('profile.danger.cancel') }}</Button>
                                        <Button
                                            variant="destructive"
                                            :disabled="deleteForm.processing || !deletePassword"
                                            @click="deleteAccount"
                                        >
                                            {{ t('profile.danger.confirm_btn') }}
                                        </Button>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </CardContent>
                    </Card>

                </div>
            </div>

        </div>
    </AppLayout>
</template>
