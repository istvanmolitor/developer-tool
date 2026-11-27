<script setup lang="ts">
import { Search, X } from '@lucide/vue';

const model = defineModel<string>({ default: '' });

withDefaults(defineProps<{ placeholder?: string }>(), {
    placeholder: 'Keresés…',
});
</script>

<template>
    <div class="relative w-full sm:max-w-xs">
        <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
            v-model="model"
            type="search"
            :placeholder="placeholder"
            :aria-label="placeholder"
            class="h-9 w-full rounded-md border border-input bg-white text-neutral-900 py-1 pr-8 pl-8 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-neutral-500 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-search-cancel-button]:hidden"
        />
        <button
            v-if="model"
            type="button"
            class="absolute top-1/2 right-2 -translate-y-1/2 rounded text-muted-foreground hover:text-foreground"
            aria-label="Keresés törlése"
            @click="model = ''"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
