<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { ArrowLeft, Bot, Pencil, Play, Zap } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import projects from "@/routes/projects";
import TaskChangeList from "../TaskChanges/TaskChangeList.vue";
import type { TaskChangeEntry } from "../TaskChanges/TaskChangeList.vue";
import TaskComments from "./TaskComments.vue";
import type { TaskComment } from "./TaskComments.vue";

type Project = { id: number; name: string; path: string };

type Task = {
    id: number;
    title: string;
    type: string;
    description: string | null;
    status: string;
    auto_run: boolean;
    priority: string;
    due_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    agent: { id: number; name: string } | null;
};

const props = defineProps<{
    project: Project;
    task: Task;
    types: { value: string; label: string }[];
    history: TaskChangeEntry[];
    comments: TaskComment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const statusLabels: Record<string, string> = {
    draft: "Piszkozat",
    todo: "Teendő",
    in_progress: "Folyamatban",
    done: "Kész",
};

const priorityLabels: Record<string, string> = {
    low: "Alacsony",
    medium: "Közepes",
    high: "Magas",
};

function typeLabel(value: string): string {
    return props.types.find((o) => o.value === value)?.label ?? value;
}

function statusVariant(status: string): "secondary" | "default" | "outline" {
    if (status === "done") return "secondary";
    if (status === "in_progress") return "default";
    return "outline";
}

function priorityVariant(
    priority: string,
): "destructive" | "default" | "outline" {
    if (priority === "high") return "destructive";
    if (priority === "medium") return "default";
    return "outline";
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString("hu-HU") : "—";
}

function run(): void {
    if (!props.task.agent) return;

    router.post(
        projects.tasks.run({ project: props.project.name, task: props.task.id })
            .url,
        {},
        { preserveScroll: true },
    );
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
                <Button
                    v-if="task.status !== 'done'"
                    size="sm"
                    :disabled="!task.agent"
                    :title="
                        task.agent
                            ? `Elindítás: ${task.agent.name} megkapja a feladatot`
                            : 'Rendelj hozzá egy agentet az elindításhoz'
                    "
                    @click="run"
                >
                    <Play class="size-4" />
                    Elindítás
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
                    <Badge
                        :variant="
                            task.type === 'bug' ? 'destructive' : 'secondary'
                        "
                    >
                        {{ typeLabel(task.type) }}
                    </Badge>
                    <Badge :variant="priorityVariant(task.priority)">
                        {{ priorityLabels[task.priority] ?? task.priority }}
                    </Badge>
                    <Badge
                        v-if="task.auto_run"
                        variant="outline"
                        class="gap-1"
                    >
                        <Zap class="size-3" />
                        Automatikus végrehajtás
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

        <section class="flex max-w-3xl flex-col gap-3">
            <h2 class="text-lg font-semibold">Hozzászólások</h2>
            <TaskComments
                :comments="comments"
                :project-name="project.name"
                :task-id="task.id"
            />
        </section>

        <section class="flex max-w-3xl flex-col gap-3">
            <h2 class="text-lg font-semibold">Változásnapló</h2>
            <TaskChangeList
                v-if="history.length > 0"
                :entries="history"
                :project-name="project.name"
            />
            <p v-else class="text-sm text-muted-foreground">
                Még nem változott a feladat státusza, prioritása, típusa,
                felelőse vagy határideje.
            </p>
        </section>
    </div>
</template>
