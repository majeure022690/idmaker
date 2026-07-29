<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        label: string;
        variant?: 'primary' | 'success' | 'warning' | 'danger';
    }>(),
    { variant: 'primary' },
);

const COLORS: Record<string, string> = {
    primary: 'text-blue-600 hover:bg-blue-50',
    success: 'text-emerald-600 hover:bg-emerald-50',
    warning: 'text-amber-600 hover:bg-amber-50',
    danger: 'text-red-600 hover:bg-red-50',
};
</script>

<template>
    <button
        :title="label"
        :aria-label="label"
        type="button"
        class="inline-flex h-8 w-8 items-center justify-center rounded border border-transparent transition-colors disabled:pointer-events-none disabled:opacity-40"
        :class="COLORS[props.variant]"
    >
        <slot />
    </button>
</template>
