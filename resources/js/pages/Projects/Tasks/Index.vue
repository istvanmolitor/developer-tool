<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import {
    Bot,
    CalendarClock,
    ChevronRight,
    ListTodo,
    Pencil,
    Plus,
    Trash2,
} from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import projects from "@/routes/projects";
import ProjectNav from "../ProjectNav.vue";

type Option = { value: string; label: string };

type Task = {
    id: number;
    title: string;
    type: string;
    description: string | null;
    status: string;
    priority: string;
    due_date: string | null;
    agent: { id: number; name: string } | null;
};

type Project = { id: number; name: string; path: string };

const props = defineProps<{
    project: Project;
    tasks: Task[];
    statuses: Option[];
    priorities: Option[];
    types: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

function statusLabel(value: string): string {
    return props.statuses.find((o) => o.value === value)?.label ?? value;
}

function priorityLabel(value: string): string {
    return props.priorities.find((o) => o.value === value)?.label ?? value;
}

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

function changeStatus(task: Task, status: string): void {
    if (status === task.status) return;

    router.patch(
        projects.tasks.update({ project: props.project.name, task: task.id })
            .url,
        { status },
        { preserveScroll: true },
    );
}

function destroy(task: Task): void {
    if (!window.confirm(`Törlöd a(z) „${task.title}” feladatot?`)) {
        return;
    }

    router.delete(
        projects.tasks.destroy({ project: props.project.name, task: task.id })
            .url,
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString("hu-HU") : "";
}
</script>

<template>
    <Head :title="`Feladatok — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="project.name" :description="project.path" />
            <Button size="sm" as-child>
                <Link :href="projects.tasks.create(project.name).url">
                    <Plus class="size-4" />
                    Új feladat
                </Link>
            </Button>
        </div>

        <ProjectNav :name="project.name" />

        <div
            v-if="tasks.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <ListTodo class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincs feladat</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Hozz létre egyet az <strong>Új feladat</strong> gombbal, vagy
                kérd meg Claude-ot az MCP-n keresztül.
            </p>
        </div>

        <div v-else class="flex flex-col gap-2">
            <Card
                v-for="task in tasks"
                :key="task.id"
                class="transition-colors hover:border-primary/50"
                :class="task.status === 'done' ? 'opacity-70' : ''"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <CardTitle class="min-w-0 text-base">
                            <Link
                                :href="
                                    projects.tasks.show({
                                        project: project.name,
                                        task: task.id,
                                    }).url
                                "
                                class="flex items-center gap-2 outline-none hover:underline focus-visible:underline"
                                :class="
                                    task.status === 'done' ? 'line-through' : ''
                                "
                            >
                                {{ task.title }}
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </CardTitle>
                        <div
                            class="mt-1.5 flex flex-wrap items-center gap-2 text-xs"
                        >
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
                                {{ priorityLabel(task.priority) }}
                            </Badge>
                            <Badge
                                v-if="task.agent"
                                variant="secondary"
                                class="gap-1"
                            >
                                <Bot class="size-3" />
                                {{ task.agent.name }}
                            </Badge>
                            <Badge
                                v-if="task.due_date"
                                variant="outline"
                                class="gap-1"
                            >
                                <CalendarClock class="size-3" />
                                {{ formatDate(task.due_date) }}
                            </Badge>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Select
                            :model-value="task.status"
                            @update:model-value="
                                (value) => changeStatus(task, String(value))
                            "
                        >
                            <SelectTrigger size="sm" class="w-36">
                                <SelectValue>
                                    {{ statusLabel(task.status) }}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    projects.tasks.edit({
                                        project: project.name,
                                        task: task.id,
                                    }).url
                                "
                            >
                                <Pencil class="size-3.5" />
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(task)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardHeader>
            </Card>
        </div>
    </div>
</template>
