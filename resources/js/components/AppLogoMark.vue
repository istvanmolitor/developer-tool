<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span
        :class="
            cn(
                'relative inline-grid size-8 shrink-0 place-items-center overflow-hidden rounded-xl bg-gradient-to-br from-[#2f79ff] via-[#1f63e6] to-[#1550c4] text-xs font-bold tracking-tight text-white shadow-[0_8px_20px_-8px_rgba(31,99,230,0.75)] ring-1 ring-white/15 select-none',
                props.class,
            )
        "
        v-bind="$attrs"
    >
        <!-- top highlight -->
        <span
            class="pointer-events-none absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/30 to-transparent"
        />
        <!-- subtle node accent -->
        <span
            class="pointer-events-none absolute top-1 right-1 size-1.5 rounded-full bg-white/80 shadow-[0_0_6px_1px_rgba(255,255,255,0.6)]"
        />
        <span class="relative leading-none">AI</span>
    </span>
</template>
