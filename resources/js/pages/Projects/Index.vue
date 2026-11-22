<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    Folder,
    FolderGit2,
    Pencil,
    Plus,
    Terminal,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import projectRoutes from '@/routes/projects';

type Project = {
    id: number;
    name: string;
    path: string;
    description: string | null;
    exists: boolean;
    is_git_repository: boolean;
    last_modified_at: string | null;
    runs_count: number;
    last_run_at: string | null;
};

defineProps<{
    projects: Project[];
    projects_path: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projectRoutes.index().url }],
    },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}

function destroy(project: Project): void {
    if (
        !window.confirm(
            `Eltávolítod a(z) „${project.name}” projektet a listáról? A lemezen lévő fájlok megmaradnak.`,
        )
    ) {
        return;
    }

    router.delete(projectRoutes.destroy(project.name).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Projektek" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Projektek"
                :description="`Claude promptok futtatása a regisztrált projektjeiden (import gyökér: ${projects_path})`"
            />
            <Button as-child>
                <Link :href="projectRoutes.create().url">
                    <Plus class="size-4" />
                    Új projekt
                </Link>
            </Button>
        </div>

        <div
            v-if="projects.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <Folder class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincsenek projektek</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Adj hozzá egyet az
                <Link
                    :href="projectRoutes.create().url"
                    class="font-medium underline"
                    >Új projekt</Link
                >
                gombbal, vagy importáld a(z)
                <code class="rounded bg-muted px-1 py-0.5">{{
                    projects_path
                }}</code>
                alatti könyvtárakat a
                <code class="rounded bg-muted px-1 py-0.5"
                    >php artisan projects:import</code
                >
                paranccsal.
            </p>
        </div>

        <div
            v-else
            class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card
                v-for="project in projects"
                :key="project.id"
                class="flex h-full flex-col transition-colors hover:border-primary/50"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <CardTitle class="min-w-0">
                            <Link
                                :href="projectRoutes.show(project.name).url"
                                class="flex items-center gap-2 break-all outline-none hover:underline focus-visible:underline"
                            >
                                <component
                                    :is="
                                        project.is_git_repository
                                            ? FolderGit2
                                            : Folder
                                    "
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                {{ project.name }}
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </CardTitle>
                    </div>
                    <p class="text-sm break-all text-muted-foreground">
                        {{ project.path }}
                    </p>
                    <p
                        v-if="project.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ project.description }}
                    </p>
                </CardHeader>
                <CardContent
                    class="mt-auto flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <Badge variant="secondary" class="gap-1">
                        <Terminal class="size-3" />
                        {{ project.runs_count }} futtatás
                    </Badge>
                    <Badge v-if="project.is_git_repository" variant="outline"
                        >git</Badge
                    >
                    <Badge
                        v-if="!project.exists"
                        variant="destructive"
                        class="gap-1"
                    >
                        <TriangleAlert class="size-3" />
                        hiányzik
                    </Badge>
                    <span class="ml-auto"
                        >Frissítve
                        {{ formatDate(project.last_modified_at) }}</span
                    >
                    <div
                        class="flex w-full items-center justify-end gap-1 pt-1"
                    >
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="projectRoutes.edit(project.name).url">
                                <Pencil class="size-3.5" />
                                Szerkesztés
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(project)"
                        >
                            <Trash2 class="size-3.5" />
                            Törlés
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
