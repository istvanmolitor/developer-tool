<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
    ChevronRight,
    Folder,
    FolderGit2,
    LoaderCircle,
    MessageSquare,
    Pencil,
    Plus,
    TriangleAlert,
} from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import projectRoutes from "@/routes/projects";

type Project = {
    id: number;
    name: string;
    path: string;
    description: string | null;
    exists: boolean;
    is_git_repository: boolean;
    is_system: boolean;
    provision_status: "ready" | "pending" | "provisioning" | "failed";
    provision_error: string | null;
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
        breadcrumbs: [{ title: "Projektek", href: projectRoutes.index().url }],
    },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}
</script>

<template>
    <Head title="Projektek" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Projektek"
                :description="`A regisztrált projektjeid (import gyökér: ${projects_path})`"
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
                    <Badge
                        v-if="
                            project.provision_status === 'pending' ||
                            project.provision_status === 'provisioning'
                        "
                        variant="secondary"
                        class="gap-1"
                    >
                        <LoaderCircle class="size-3 animate-spin" />
                        létrehozás folyamatban
                    </Badge>
                    <Badge
                        v-else-if="project.provision_status === 'failed'"
                        variant="destructive"
                        class="gap-1"
                        :title="project.provision_error ?? undefined"
                    >
                        <TriangleAlert class="size-3" />
                        létrehozás sikertelen
                    </Badge>
                    <Badge
                        v-else-if="!project.exists"
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
                        <Button variant="ghost" size="icon-sm" as-child>
                            <Link
                                :href="projectRoutes.prompts(project.name).url"
                                :title="`Chat a(z) ${project.name} projekten`"
                                :aria-label="`Chat a(z) ${project.name} projekten`"
                            >
                                <MessageSquare class="size-4" />
                            </Link>
                        </Button>
                        <Button variant="ghost" size="icon-sm" as-child>
                            <Link
                                :href="projectRoutes.edit(project.name).url"
                                :title="`${project.name} szerkesztése`"
                                :aria-label="`${project.name} szerkesztése`"
                            >
                                <Pencil class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
