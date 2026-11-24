<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bot, Pencil } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import projects from '@/routes/projects';

type Project = { id: number; name: string; path: string };

type Task = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    due_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    agent: { id: number; name: string } | null;
};

const props = defineProps<{
    project: Project;
    task: Task;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projects.index().url }],
    },
});

const statusLabels: Record<string, string> = {
    todo: 'Teendő',
    in_progress: 'Folyamatban',
    done: 'Kész',
};

const priorityLabels: Record<string, string> = {
    low: 'Alacsony',
    medium: 'Közepes',
    high: 'Magas',
};

function statusVariant(status: string): 'secondary' | 'default' | 'outline' {
    if (status === 'done') return 'secondary';
    if (status === 'in_progress') return 'default';
    return 'outline';
}

function priorityVariant(
    priority: string,
): 'destructive' | 'default' | 'outline' {
    if (priority === 'high') return 'destructive';
    if (priority === 'medium') return 'default';
    return 'outline';
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('hu-HU') : '—';
}
</script>

<template>
    <Head :title="`${task.title} — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="task.title" :description="project.name" />
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="projects.tasks.index(project.name).url">
                        <ArrowLeft class="size-4" />
                        Vissza
                    </Link>
                </Button>
                <Button size="sm" as-child>
                    <Link
                        :href="
                            projects.tasks.edit({
                                project: project.name,
                                task: task.id,
                            }).url
                        "
                    >
                        <Pencil class="size-4" />
                        Szerkesztés
                    </Link>
                </Button>
            </div>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="flex flex-wrap items-center gap-2">
                    <Badge :variant="statusVariant(task.status)">
                        {{ statusLabels[task.status] ?? task.status }}
                    </Badge>
                    <Badge :variant="priorityVariant(task.priority)">
                        {{ priorityLabels[task.priority] ?? task.priority }}
                    </Badge>
                    <Badge v-if="task.agent" variant="secondary" class="gap-1">
                        <Bot class="size-3" />
                        {{ task.agent.name }}
                    </Badge>
                    <span class="text-sm font-normal text-muted-foreground">
                        Határidő: {{ formatDate(task.due_date) }}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <p
                    v-if="task.description"
                    class="text-sm whitespace-pre-wrap text-foreground"
                >
                    {{ task.description }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Nincs leírás.
                </p>
                <p class="text-xs text-muted-foreground">
                    Létrehozva: {{ formatDate(task.created_at) }} · Frissítve:
                    {{ formatDate(task.updated_at) }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
