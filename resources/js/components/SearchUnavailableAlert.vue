<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400"
    >
        <TriangleAlert class="size-4 shrink-0" />
        Az Elasticsearch most nem érhető el — a lista közvetlenül az
        adatbázisból töltődött, ez lassabb lehet.
    </div>
</template>
