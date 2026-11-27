<script setup lang="ts">
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import {
    BookText,
    ChevronRight,
    Download,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
} from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import ListSearchInput from "@/components/ListSearchInput.vue";
import PaginationBar from "@/components/PaginationBar.vue";
import type { Paginator } from "@/components/PaginationBar.vue";
import SearchUnavailableAlert from "@/components/SearchUnavailableAlert.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useListFilters } from "@/composables/useListFilters";
import projects from "@/routes/projects";
import ProjectNav from "../ProjectNav.vue";

type Document = {
    id: number;
    path: string;
    title: string | null;
    updated_at: string | null;
};

type Project = {
    id: number;
    name: string;
    path: string;
};

const props = defineProps<{
    project: Project;
    documents: Paginator & { data: Document[] };
    filters: { q: string };
    search_degraded: boolean;
    has_documents: boolean;
    documents_path: string;
}>();

const { filters, isFiltered } = useListFilters({ ...props.filters });

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const syncForm = useForm({});

function sync(): void {
    syncForm.post(projects.documents.sync(props.project.name).url, {
        preserveScroll: true,
    });
}

function destroy(doc: Document): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${doc.path}” dokumentumot az adatbázisból? A lemezen lévő fájl megmarad.`,
        )
    ) {
        return;
    }

    router.delete(
        projects.documents.destroy({
            project: props.project.name,
            document: doc.id,
        }).url,
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}
</script>

<template>
    <Head :title="`Dokumentumok — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="project.name"
                description="A projekt docs mappájának Markdown fájljai."
            />
            <div class="flex shrink-0 items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="syncForm.processing"
                    @click="sync"
                >
                    <RefreshCw
                        class="size-4"
                        :class="syncForm.processing ? 'animate-spin' : ''"
                    />
                    {{ syncForm.processing ? "Betöltés…" : "Frissítés" }}
                </Button>
                <Button
                    v-if="has_documents"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <a
                        :href="projects.documents.pdf(project.name).url"
                        download
                    >
                        <Download class="size-4" />
                        Letöltés PDF
                    </a>
                </Button>
                <Button size="sm" as-child>
                    <Link :href="projects.documents.create(project.name).url">
                        <Plus class="size-4" />
                        Új dokumentum
                    </Link>
                </Button>
            </div>
        </div>

        <ProjectNav :name="project.name" />

        <ListSearchInput
            v-model="filters.q"
            placeholder="Keresés a dokumentumokban…"
        />

        <SearchUnavailableAlert v-if="search_degraded" />

        <div
            v-if="documents.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <BookText class="mx-auto mb-3 size-8 text-muted-foreground" />
            <template v-if="isFiltered()">
                <p class="font-medium">Nincs találat</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Egyik dokumentum sem tartalmazza a keresett kifejezést.
                </p>
            </template>
            <template v-else>
                <p class="font-medium">Még nincs dokumentum</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Nyomd meg a <strong>Frissítés</strong> gombot a lemezen lévő
                    fájlok betöltéséhez, vagy hozz létre egyet kézzel.
                </p>
            </template>
        </div>

        <div v-else class="flex flex-col gap-2">
            <Card
                v-for="doc in documents.data"
                :key="doc.id"
                class="transition-colors hover:border-primary/50"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <CardTitle class="min-w-0 text-base">
                            <Link
                                :href="
                                    projects.documents.show({
                                        project: project.name,
                                        document: doc.id,
                                    }).url
                                "
                                class="flex items-center gap-2 break-all outline-none hover:underline focus-visible:underline"
                            >
                                <BookText
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                {{ doc.title ?? doc.path }}
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </CardTitle>
                        <p class="mt-1 font-mono text-xs text-muted-foreground">
                            {{ doc.path }} · frissítve
                            {{ formatDate(doc.updated_at) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    projects.documents.edit({
                                        project: project.name,
                                        document: doc.id,
                                    }).url
                                "
                            >
                                <Pencil class="size-3.5" />
                                Szerkesztés
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(doc)"
                        >
                            <Trash2 class="size-3.5" />
                            Törlés
                        </Button>
                    </div>
                </CardHeader>
            </Card>
        </div>

        <PaginationBar :paginator="documents" noun="dokumentum" />
    </div>
</template>
