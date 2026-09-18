export { default as Toast } from "./Toast.vue";
export { default as ToastAction } from "./ToastAction.vue";
export { default as ToastClose } from "./ToastClose.vue";
export { default as ToastDescription } from "./ToastDescription.vue";
export { default as Toaster } from "./Toaster.vue";
export { default as ToastProvider } from "./ToastProvider.vue";
export { default as ToastTitle } from "./ToastTitle.vue";
export { default as ToastViewport } from "./ToastViewport.vue";
export { toast, useToast } from "./use-toast";

import { cva } from "class-variance-authority";

export const toastVariants = cva(
  "group pointer-events-auto relative z-[10001] flex w-full items-center justify-between gap-2 overflow-hidden rounded-md border bg-white dark:bg-neutral-900 backdrop-blur-0 p-4 pe-6 shadow-xl ring-1 ring-black/5 transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--reka-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--reka-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-bottom-full",
  {
    variants: {
      variant: {
        default: "border bg-white dark:bg-neutral-900 text-foreground",
        // Call sites have long passed `variant: 'success'`, which cva had no
        // entry for, so those toasts fell back to the white surface and were
        // only green because each one also hand-rolled a `bg-green-600` class.
        success:
          "success group border-transparent bg-green-600 text-white dark:bg-green-700",
        warning:
          "warning group border-transparent bg-amber-500 text-white dark:bg-amber-600",
        info: "info group border-transparent bg-sky-600 text-white dark:bg-sky-700",
        destructive:
          "destructive group border-destructive bg-destructive text-destructive-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);
