<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FolderGit2, Folder, Terminal } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import projects from '@/routes/projects';

type Project = {
    name: string;
    path: string;
    last_modified_at: string | null;
    is_git_repository: boolean;
    runs_count: number;
    last_run_at: string | null;
};

defineProps<{
    projects: Project[];
    projects_path: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projects.index().url }],
    },
});

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Projects"
            :description="`Run claude prompts against the projects in ${projects_path}`"
        />

        <div v-if="projects.length === 0" class="rounded-xl border border-dashed p-10 text-center">
            <Folder class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">No projects found</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Add a project directory to
                <code class="rounded bg-muted px-1 py-0.5">{{ projects_path }}</code>
                to get started.
            </p>
        </div>

        <div v-else class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="project in projects"
                :key="project.name"
                :href="projects.show(project.name).url"
                class="group rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <Card class="h-full transition-colors group-hover:border-primary/50">
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="flex items-center gap-2 break-all">
                                <component
                                    :is="project.is_git_repository ? FolderGit2 : Folder"
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                {{ project.name }}
                            </CardTitle>
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                            />
                        </div>
                        <CardDescription class="break-all">{{ project.path }}</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant="secondary" class="gap-1">
                            <Terminal class="size-3" />
                            {{ project.runs_count }} {{ project.runs_count === 1 ? 'run' : 'runs' }}
                        </Badge>
                        <Badge v-if="project.is_git_repository" variant="outline">git</Badge>
                        <span class="ml-auto">Updated {{ formatDate(project.last_modified_at) }}</span>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
