<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { Loader2, Search } from '@lucide/vue';
import { onClickOutside, onKeyStroke, useDebounceFn } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';
import search from '@/routes/search';

type Suggestion = {
    key: string;
    type: string;
    type_label: string;
    title: string;
    title_html: string;
    snippet: string | null;
    url: string | null;
    project: string | null;
};

type SuggestResponse = { results: Suggestion[]; total: number };

const root = useTemplateRef<HTMLElement>('root');
const input = useTemplateRef<HTMLInputElement>('input');

const http = useHttp<{ q: string }, SuggestResponse>({ q: '' });

const open = ref(false);
const activeIndex = ref(-1);
const results = ref<Suggestion[]>([]);
const total = ref(0);

const query = computed({
    get: () => http.q,
    set: (value: string) => (http.q = value),
});

const fetchSuggestions = useDebounceFn(async () => {
    if (http.q.trim().length < 2) {
        results.value = [];
        total.value = 0;

        return;
    }

    http.cancel();

    try {
        const response = await http.get(search.suggest().url);
        results.value = response.results;
        total.value = response.total;
        activeIndex.value = -1;
    } catch {
        // A cancelled or failed lookup simply keeps the previous suggestions.
    }
}, 200);

watch(
    () => http.q,
    () => {
        open.value = true;
        fetchSuggestions();
    },
);

onClickOutside(root, () => (open.value = false));

// "/" or Ctrl/Cmd+K jumps into the search from anywhere outside a text field.
onKeyStroke(['/', 'k'], (event) => {
    const target = event.target as HTMLElement | null;
    const typing =
        target?.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName ?? '');

    if (event.key === 'k' && !(event.ctrlKey || event.metaKey)) {
        return;
    }

    if (event.key === '/' && typing) {
        return;
    }

    event.preventDefault();
    input.value?.focus();
    open.value = true;
});

function go(suggestion: Suggestion): void {
    if (!suggestion.url) {
        return;
    }

    open.value = false;
    router.visit(suggestion.url);
}

function showAll(): void {
    const q = http.q.trim();

    if (q === '') {
        return;
    }

    open.value = false;
    router.visit(search.index({ query: { q } }).url);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            results.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, -1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const active = results.value[activeIndex.value];

        if (active) {
            go(active);
        } else {
            showAll();
        }
    } else if (event.key === 'Escape') {
        open.value = false;
        input.value?.blur();
    }
}
</script>

<template>
    <div ref="root" class="relative w-full max-w-md">
        <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
            ref="input"
            v-model="query"
            type="search"
            role="combobox"
            aria-label="Keresés"
            aria-autocomplete="list"
            aria-controls="global-search-results"
            :aria-expanded="open && query.trim().length >= 2"
            placeholder="Keresés feladatban, doksiban, promptban, levélben…"
            class="h-9 w-full rounded-md border border-input bg-transparent py-1 pr-12 pl-8 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30 [&::-webkit-search-cancel-button]:hidden"
            @focus="open = true"
            @keydown="onKeydown"
        />
        <Loader2
            v-if="http.processing"
            class="absolute top-1/2 right-2.5 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
        />
        <kbd
            v-else-if="!query"
            class="pointer-events-none absolute top-1/2 right-2 hidden -translate-y-1/2 rounded border px-1.5 font-mono text-[10px] text-muted-foreground sm:block"
            >/</kbd
        >

        <div
            v-if="open && query.trim().length >= 2"
            id="global-search-results"
            role="listbox"
            class="absolute top-full right-0 left-0 z-50 mt-1 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
        >
            <p
                v-if="results.length === 0 && !http.processing"
                class="px-3 py-4 text-center text-sm text-muted-foreground"
            >
                Nincs találat.
            </p>

            <ul v-else class="max-h-96 overflow-y-auto py-1">
                <li
                    v-for="(result, index) in results"
                    :key="result.key"
                    role="option"
                    :aria-selected="index === activeIndex"
                >
                    <button
                        type="button"
                        class="flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent disabled:cursor-default disabled:opacity-60"
                        :class="index === activeIndex ? 'bg-accent' : ''"
                        :disabled="!result.url"
                        @mouseenter="activeIndex = index"
                        @click="go(result)"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                >{{ result.type_label }}</span
                            >
                            <!-- eslint-disable-next-line vue/no-v-html -- server-escaped highlight -->
                            <span
                                class="search-highlight truncate font-medium"
                                v-html="result.title_html"
                            />
                            <span
                                v-if="result.project"
                                class="ml-auto shrink-0 text-xs text-muted-foreground"
                                >{{ result.project }}</span
                            >
                        </span>
                        <!-- eslint-disable-next-line vue/no-v-html -- server-escaped highlight -->
                        <span
                            v-if="result.snippet"
                            class="search-highlight line-clamp-1 text-xs text-muted-foreground"
                            v-html="result.snippet"
                        />
                    </button>
                </li>
            </ul>

            <button
                type="button"
                class="flex w-full items-center justify-between border-t px-3 py-2 text-left text-xs text-muted-foreground hover:bg-accent"
                @click="showAll"
            >
                <span>Összes találat megjelenítése</span>
                <span>{{ total }} db · Enter</span>
            </button>
        </div>
    </div>
</template>
