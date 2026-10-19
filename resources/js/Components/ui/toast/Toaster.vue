<script setup>
import { isVNode } from "vue";
import {
  Toast,
  ToastClose,
  ToastDescription,
  ToastProvider,
  ToastTitle,
  ToastViewport,
} from ".";
import { useToast } from "./use-toast";

const { toasts } = useToast();
</script>

<template>
  <ToastProvider>
    
    <Toast v-for="toast in toasts" :key="toast.id" v-bind="toast">
      <div class="grid gap-1">
        <ToastTitle v-if="toast.title">
          {{ toast.title }}
        </ToastTitle>
        <template v-if="toast.description">
          <ToastDescription v-if="isVNode(toast.description)">
            <component :is="toast.description" />
          </ToastDescription>
          <ToastDescription v-else>
            {{ toast.description }}
          </ToastDescription>
        </template>
      </div>
      <component :is="toast.action" />
      <!-- Absolutely positioned against the toast root, so it belongs beside
           the text column rather than inside it. -->
      <ToastClose />
    </Toast>

    <ToastViewport />
  </ToastProvider>
</template>
