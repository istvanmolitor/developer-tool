<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { SearchX } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ListSearchInput from '@/components/ListSearchInput.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import SearchUnavailableAlert from '@/components/SearchUnavailableAlert.vue';
import { Card, CardHeader } from '@/components/ui/card';
import search from '@/routes/search';

type TypeOption = { value: string; label: string; count: number };

type Result = {
    key: string;
    type: string;
    type_label: string;
    title: string;
    title_html: string;
    snippet: string | null;
    url: string | null;
    project: string | null;
    created_at: string | null;
};

const props = defineProps<{
    filters: { q: string; type: string | null };
    types: TypeOption[];
    results: {
        data: Result[];
        total: number;
        current_page: number;
        last_page: number;
    } | null;
    search_degraded: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Keresés', href: search.index().url }],
    },
});

const query = ref(props.filters.q);

function visit(params: {
    q: string;
    type: string | null;
    page?: number;
}): void {
    router.get(
        search.index().url,
        {
            q: params.q || undefined,
            type: params.type ?? undefined,
            page: params.page && params.page > 1 ? params.page : undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(
    query,
    useDebounceFn((q: string) => visit({ q, type: props.filters.type }), 300),
);

function pageUrl(page: number): string | null {
    if (!props.results || page < 1 || page > props.results.last_page) {
        return null;
    }

    return search.index({
        query: {
            q: props.filters.q,
            ...(props.filters.type ? { type: props.filters.type } : {}),
            ...(page > 1 ? { page } : {}),
        },
    }).url;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '';
}
</script>

<template>
    <Head :title="filters.q ? `Keresés: ${filters.q}` : 'Keresés'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Keresés"
            description="Feladatok, dokumentumok, promptok, üzenetek, levelek és kommentek."
        />

        <ListSearchInput
            v-model="query"
            placeholder="Mit keresel?"
            class="sm:max-w-xl"
        />

        <SearchUnavailableAlert v-if="search_degraded" />

        <div v-if="results" class="flex flex-wrap gap-1.5">
            <button
                type="button"
                class="rounded-full border px-3 py-1 text-xs transition-colors"
                :class="
                    filters.type === null
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                "
                @click="visit({ q: filters.q, type: null })"
            >
                Mind
                <span class="opacity-70">{{
                    types.reduce((sum, t) => sum + t.count, 0)
                }}</span>
            </button>
            <button
                v-for="type in types"
                :key="type.value"
                type="button"
                class="rounded-full border px-3 py-1 text-xs transition-colors disabled:opacity-40"
                :class="
                    filters.type === type.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                "
                :disabled="type.count === 0 && filters.type !== type.value"
                @click="visit({ q: filters.q, type: type.value })"
            >
                {{ type.label }}
                <span class="opacity-70">{{ type.count }}</span>
            </button>
        </div>

        <p
            v-if="!results"
            class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            Írj be egy kifejezést a kereséshez.
        </p>

        <div
            v-else-if="results.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <SearchX class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Nincs találat</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Próbálj más kifejezést, vagy válassz másik típust.
            </p>
        </div>

        <template v-else>
            <div class="flex flex-col gap-2">
                <Card
                    v-for="result in results.data"
                    :key="result.key"
                    class="transition-colors hover:border-primary/50"
                >
                    <CardHeader class="gap-1.5">
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 font-medium tracking-wide uppercase"
                                >{{ result.type_label }}</span
                            >
                            <span v-if="result.project">{{
                                result.project
                            }}</span>
                            <span v-if="result.created_at">
                                · {{ formatDate(result.created_at) }}</span
                            >
                        </div>
                        <Link
                            v-if="result.url"
                            :href="result.url"
                            class="search-highlight font-medium hover:underline"
                        >
                            <!-- eslint-disable-next-line vue/no-v-html -- server-escaped highlight -->
                            <span v-html="result.title_html" />
                        </Link>
                        <!-- eslint-disable-next-line vue/no-v-html -- server-escaped highlight -->
                        <span
                            v-else
                            class="search-highlight font-medium"
                            v-html="result.title_html"
                        />
                        <!-- eslint-disable-next-line vue/no-v-html -- server-escaped highlight -->
                        <p
                            v-if="result.snippet"
                            class="search-highlight line-clamp-2 text-sm break-words text-muted-foreground"
                            v-html="result.snippet"
                        />
                    </CardHeader>
                </Card>
            </div>

            <PaginationBar
                :paginator="{
                    current_page: results.current_page,
                    last_page: results.last_page,
                    total: results.total,
                    prev_page_url: pageUrl(results.current_page - 1),
                    next_page_url: pageUrl(results.current_page + 1),
                }"
            />
        </template>
    </div>
</template>
