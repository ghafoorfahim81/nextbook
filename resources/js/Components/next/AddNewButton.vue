<template>
    <Button
        :variant="variant"
        :size="size"
        :class="buttonClass"
        @click="handleClick"
        :disabled="disabled"
    >
        <Plus class="w-4 h-4" />
        <span>{{ t('general.add', { title: title }) }}</span>
    </Button>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Plus } from 'lucide-vue-next'

const props = defineProps({
    // Button appearance
    variant: {
        type: String,
        default: 'default'
    },
    size: {
        type: String,
        default: 'default'
    },
    title: {
        type: String,
        default: null
    },
    buttonClass: {
        type: String,
        default: ''
    },
    disabled: {
        type: Boolean,
        default: false
    },


    // Action configuration
    action: {
        type: String,
        required: true,
        validator: (value) => ['modal', 'redirect'].includes(value)
    },

    // For modal action
    modalId: {
        type: String,
        default: null
    },

    // For redirect action
    route: {
        type: String,
        default: null
    },
    routeParams: {
        type: Object,
        default: () => ({})
    },

    // Additional click handler
    onClick: {
        type: Function,
        default: null
    }
})

const emit = defineEmits(['click', 'modal-open', 'redirect'])

const { t } = useI18n()

const handleClick = () => {
    // Emit click event for parent handling
    emit('click')

    // Call custom onClick handler if provided
    if (props.onClick) {
        props.onClick()
    }

    if (props.action === 'modal') {
        // Emit modal open event
        emit('modal-open', props.modalId)
    } else if (props.action === 'redirect') {
        // Handle redirect
        if (props.route) {
            router.visit(route(props.route, props.routeParams))
        }
        emit('redirect', props.route, props.routeParams)
    }
}
</script>
