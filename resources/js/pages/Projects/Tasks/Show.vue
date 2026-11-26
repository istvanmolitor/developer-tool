<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { ArrowLeft, Bot, Pencil, Play, ScrollText, User, Zap } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import projects from "@/routes/projects";
import TaskChangeList from "../TaskChanges/TaskChangeList.vue";
import type { TaskChangeEntry } from "../TaskChanges/TaskChangeList.vue";
import TaskAttachments from "./TaskAttachments.vue";
import type { TaskAttachment } from "./TaskAttachments.vue";
import TaskComments from "./TaskComments.vue";
import type { TaskComment } from "./TaskComments.vue";

type Project = { id: number; name: string; path: string };

type Creator = { type: "user" | "agent" | null; name: string | null };

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
    creator: Creator;
};

const props = defineProps<{
    project: Project;
    task: Task;
    types: { value: string; label: string }[];
    history: TaskChangeEntry[];
    comments: TaskComment[];
    attachments: TaskAttachment[];
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
    closed: "Lezárt",
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
    if (status === "done" || status === "closed") return "secondary";
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
                    v-if="task.status !== 'done' && task.status !== 'closed'"
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Bal oldal: a feladat és a kommentek -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex flex-wrap items-center gap-2">
                            <Badge :variant="statusVariant(task.status)">
                                {{ statusLabels[task.status] ?? task.status }}
                            </Badge>
                            <Badge
                                :variant="
                                    task.type === 'bug'
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ typeLabel(task.type) }}
                            </Badge>
                            <Badge :variant="priorityVariant(task.priority)">
                                {{
                                    priorityLabels[task.priority] ??
                                    task.priority
                                }}
                            </Badge>
                            <Badge
                                v-if="task.auto_run"
                                variant="outline"
                                class="gap-1"
                            >
                                <Zap class="size-3" />
                                Automatikus végrehajtás
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="task.description"
                            class="text-sm whitespace-pre-wrap text-foreground"
                        >
                            {{ task.description }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Nincs leírás.
                        </p>
                    </CardContent>
                </Card>

                <section class="flex flex-col gap-3">
                    <h2 class="text-lg font-semibold">Hozzászólások</h2>
                    <TaskComments
                        :comments="comments"
                        :project-name="project.name"
                        :task-id="task.id"
                    />
                </section>
            </div>

            <!-- Jobb oldal: a többi -->
            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Részletek</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">Felelős</span>
                            <Badge
                                v-if="task.agent"
                                variant="secondary"
                                class="gap-1"
                            >
                                <Bot class="size-3" />
                                {{ task.agent.name }}
                            </Badge>
                            <span v-else class="text-muted-foreground"
                                >Nincs</span
                            >
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">Létrehozta</span>
                            <Badge
                                v-if="task.creator.type"
                                variant="outline"
                                class="gap-1"
                            >
                                <Bot
                                    v-if="task.creator.type === 'agent'"
                                    class="size-3"
                                />
                                <User v-else class="size-3" />
                                {{ task.creator.name ?? "Ismeretlen" }}
                            </Badge>
                            <span v-else class="text-muted-foreground"
                                >Ismeretlen</span
                            >
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">Határidő</span>
                            <span>{{ formatDate(task.due_date) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">Létrehozva</span>
                            <span>{{ formatDate(task.created_at) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">Frissítve</span>
                            <span>{{ formatDate(task.updated_at) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <section class="flex flex-col gap-3">
                    <h2 class="text-lg font-semibold">Csatolmányok</h2>
                    <TaskAttachments
                        :attachments="attachments"
                        :project-name="project.name"
                        :task-id="task.id"
                    />
                </section>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="outline" class="w-full justify-start">
                            <ScrollText class="size-4" />
                            Változásnapló
                        </Button>
                    </DialogTrigger>
                    <DialogScrollContent class="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Változásnapló</DialogTitle>
                            <DialogDescription>
                                A feladat státuszának, prioritásának, típusának,
                                felelősének és határidejének módosításai.
                            </DialogDescription>
                        </DialogHeader>
                        <TaskChangeList
                            v-if="history.length > 0"
                            :entries="history"
                            :project-name="project.name"
                        />
                        <p v-else class="text-sm text-muted-foreground">
                            Még nem változott a feladat státusza, prioritása,
                            típusa, felelőse vagy határideje.
                        </p>
                    </DialogScrollContent>
                </Dialog>
            </div>
        </div>
    </div>
</template>
