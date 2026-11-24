<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { CalendarClock, ChevronRight, FolderGit2, ListTodo } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Card, CardHeader, CardTitle } from "@/components/ui/card";
import agents from "@/routes/agents";
import projects from "@/routes/projects";
import AgentNav from "./AgentNav.vue";

type Agent = {
    id: number;
    name: string;
    slug: string;
};

type Option = {
    value: string;
    label: string;
};

type Task = {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date: string | null;
    created_at: string;
    project: { id: number; name: string };
};

const props = defineProps<{
    agent: Agent;
    tasks: Task[];
    statuses: Option[];
    priorities: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Agentek", href: agents.index().url }],
    },
});

function statusLabel(value: string): string {
    return props.statuses.find((o) => o.value === value)?.label ?? value;
}

function priorityLabel(value: string): string {
    return props.priorities.find((o) => o.value === value)?.label ?? value;
}

function priorityVariant(
    priority: string,
): "destructive" | "default" | "secondary" {
    if (priority === "high") {
        return "destructive";
    }

    if (priority === "medium") {
        return "default";
    }

    return "secondary";
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString("hu-HU");
}
</script>

<template>
    <Head :title="`${agent.name} feladatai — Agentek`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <Heading :title="agent.name" variant="small" />
            <p class="font-mono text-xs text-muted-foreground">
                {{ agent.slug }}
            </p>
        </div>

        <AgentNav :agent-id="agent.id" />

        <p class="text-xs text-muted-foreground">
            Nyitott feladatok — a magasabb prioritás elöl, azon belül a
            legrégebbi.
        </p>

        <div
            v-if="tasks.length === 0"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <ListTodo class="size-8" />
            Nincs az agenthez rendelt nyitott feladat.
        </div>

        <div v-else class="flex flex-col gap-2">
            <Card
                v-for="task in tasks"
                :key="task.id"
                class="transition-colors hover:border-primary/50"
            >
                <CardHeader>
                    <CardTitle class="min-w-0 text-base">
                        <Link
                            :href="
                                projects.tasks.show({
                                    project: task.project.name,
                                    task: task.id,
                                }).url
                            "
                            class="flex items-center gap-2 outline-none hover:underline focus-visible:underline"
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
                        <Badge :variant="priorityVariant(task.priority)">
                            {{ priorityLabel(task.priority) }}
                        </Badge>
                        <Badge
                            :variant="
                                task.status === 'in_progress'
                                    ? 'default'
                                    : 'outline'
                            "
                        >
                            {{ statusLabel(task.status) }}
                        </Badge>
                        <Badge variant="secondary" class="gap-1">
                            <FolderGit2 class="size-3" />
                            {{ task.project.name }}
                        </Badge>
                        <Badge
                            v-if="task.due_date"
                            variant="outline"
                            class="gap-1"
                        >
                            <CalendarClock class="size-3" />
                            {{ formatDate(task.due_date) }}
                        </Badge>
                        <span class="text-muted-foreground">
                            létrehozva {{ formatDate(task.created_at) }}
                        </span>
                    </div>
                </CardHeader>
            </Card>
        </div>
    </div>
</template>
