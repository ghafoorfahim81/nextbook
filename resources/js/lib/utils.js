import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...classes) {
    return twMerge(clsx(...classes));
}

export function valueUpdater(updaterOrValue, ref) {
    ref.value = typeof updaterOrValue === 'function'
        ? updaterOrValue(ref.value)
        : updaterOrValue
}
