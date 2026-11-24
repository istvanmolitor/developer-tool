<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FolderGit2,
    Pencil,
    Terminal,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import projects from '@/routes/projects';
import ProjectNav from './ProjectNav.vue';

type Project = {
    id: number;
    name: string;
    path: string;
    git_url: string | null;
    description: string | null;
    exists: boolean;
    is_git_repository: boolean;
    is_system: boolean;
    last_modified_at: string | null;
};

const props = defineProps<{
    project: Project;
    runs_count: number;
    last_run_at: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projects.index().url }],
    },
});

function destroy(): void {
    if (
        !window.confirm(
            `Eltávolítod a(z) „${props.project.name}” projektet a listáról? A lemezen lévő fájlok megmaradnak.`,
        )
    ) {
        return;
    }

    router.delete(projects.destroy(props.project.name).url);
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head :title="`${project.name} — Projektek`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="project.name" :description="project.path" />
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="projects.edit(project.name).url">
                        <Pencil class="size-4" />
                        Szerkesztés
                    </Link>
                </Button>
                <Button
                    v-if="!project.is_system"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Törlés
                </Button>
            </div>
        </div>

        <ProjectNav :name="project.name" />

        <div
            v-if="!project.exists"
            class="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            <TriangleAlert class="size-4 shrink-0" />
            A(z) <code class="font-mono">{{ project.path }}</code> könyvtár nem
            létezik — a promptok hibára futnak, amíg létre nem jön.
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Részletek</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4 text-sm">
                <div v-if="project.description">
                    <p class="text-xs font-medium text-muted-foreground">
                        Leírás
                    </p>
                    <p class="mt-1">{{ project.description }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Útvonal
                    </p>
                    <p class="mt-1 font-mono break-all">{{ project.path }}</p>
                </div>

                <div v-if="project.git_url">
                    <p class="text-xs font-medium text-muted-foreground">
                        Git link
                    </p>
                    <a
                        :href="project.git_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 inline-block font-mono break-all text-primary underline-offset-4 hover:underline"
                    >
                        {{ project.git_url }}
                    </a>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Badge v-if="project.is_system" variant="outline">
                        rendszer
                    </Badge>
                    <Badge
                        v-if="project.is_git_repository"
                        variant="outline"
                        class="gap-1"
                    >
                        <FolderGit2 class="size-3" />
                        git tároló
                    </Badge>
                    <Badge variant="secondary" class="gap-1">
                        <Terminal class="size-3" />
                        {{ runs_count }} futtatás
                    </Badge>
                    <span class="text-xs text-muted-foreground">
                        Frissítve {{ formatDate(project.last_modified_at) }}
                        <template v-if="last_run_at">
                            · utolsó futtatás {{ formatDate(last_run_at) }}
                        </template>
                    </span>
                </div>

                <div class="pt-1">
                    <Button as-child>
                        <Link :href="projects.prompts(project.name).url">
                            <Terminal class="size-4" />
                            Ugrás a promptokhoz
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
