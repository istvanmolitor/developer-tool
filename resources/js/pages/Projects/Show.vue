<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { Pencil, Trash2, TriangleAlert } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import projects from "@/routes/projects";
import ProjectNav from "./ProjectNav.vue";

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
    last_run_at: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
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
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}
</script>

<template>
    <Head :title="`${project.name} — Projektek`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="project.name"
                :description="project.is_system ? 'Rendszerprojekt' : undefined"
            />
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

        <Card v-if="project.description">
            <CardHeader>
                <CardTitle class="text-base">Leírás</CardTitle>
            </CardHeader>
            <CardContent class="text-sm whitespace-pre-line">
                {{ project.description }}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Hely</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Útvonal
                        </dt>
                        <dd class="mt-1 font-mono break-all">
                            {{ project.path }}
                        </dd>
                    </div>
                    <div v-if="project.git_url">
                        <dt class="text-xs font-medium text-muted-foreground">
                            Git link
                        </dt>
                        <dd class="mt-1">
                            <a
                                :href="project.git_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-mono break-all text-primary underline-offset-4 hover:underline"
                            >
                                {{ project.git_url }}
                            </a>
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Aktivitás</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Mappa frissítve
                        </dt>
                        <dd class="mt-1">
                            {{ formatDate(project.last_modified_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Utolsó futtatás
                        </dt>
                        <dd class="mt-1">{{ formatDate(last_run_at) }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
