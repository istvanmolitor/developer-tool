<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

export type Paginator = {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

withDefaults(defineProps<{ paginator: Paginator; noun?: string }>(), {
    noun: 'találat',
});
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
    >
        <span>
            {{ paginator.total }} {{ noun }}
            <template v-if="paginator.last_page > 1">
                · {{ paginator.current_page }} / {{ paginator.last_page }}
                oldal
            </template>
        </span>
        <div v-if="paginator.last_page > 1" class="flex gap-1">
            <Button
                variant="outline"
                size="sm"
                :disabled="!paginator.prev_page_url"
                :as-child="!!paginator.prev_page_url"
            >
                <Link
                    v-if="paginator.prev_page_url"
                    :href="paginator.prev_page_url"
                    preserve-state
                >
                    <ChevronLeft class="size-4" /> Előző
                </Link>
                <template v-else>
                    <ChevronLeft class="size-4" /> Előző
                </template>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!paginator.next_page_url"
                :as-child="!!paginator.next_page_url"
            >
                <Link
                    v-if="paginator.next_page_url"
                    :href="paginator.next_page_url"
                    preserve-state
                >
                    Következő <ChevronRight class="size-4" />
                </Link>
                <template v-else>
                    Következő <ChevronRight class="size-4" />
                </template>
            </Button>
        </div>
    </div>
</template>
