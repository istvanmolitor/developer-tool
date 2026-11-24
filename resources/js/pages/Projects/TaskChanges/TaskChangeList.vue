<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowRight, Bot, User } from "@lucide/vue";
import projects from "@/routes/projects";

export type TaskChangeEntry = {
    id: number;
    created_at: string | null;
    task: { id: number; title: string } | null;
    actor: { type: "user" | "agent" | null; name: string | null };
    changes: {
        field: string;
        label: string;
        old: string | null;
        new: string | null;
    }[];
};

defineProps<{
    entries: TaskChangeEntry[];
    projectName: string;
    showTask?: boolean;
}>();

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}

function actorName(actor: TaskChangeEntry["actor"]): string {
    if (actor.type === null) return "Ismeretlen (MCP)";
    return (
        actor.name ??
        (actor.type === "agent" ? "Törölt agent" : "Törölt felhasználó")
    );
}
</script>

<template>
    <ul class="flex flex-col divide-y rounded-xl border">
        <li
            v-for="entry in entries"
            :key="entry.id"
            class="flex flex-col gap-2 p-4 text-sm"
        >
            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground"
            >
                <span
                    class="flex items-center gap-1 font-medium text-foreground"
                >
                    <Bot v-if="entry.actor.type === 'agent'" class="size-4" />
                    <User v-else class="size-4" />
                    {{ actorName(entry.actor) }}
                </span>
                <span>{{ formatDateTime(entry.created_at) }}</span>
                <Link
                    v-if="showTask && entry.task"
                    :href="
                        projects.tasks.show({
                            project: projectName,
                            task: entry.task.id,
                        }).url
                    "
                    class="truncate text-foreground hover:underline"
                >
                    #{{ entry.task.id }} {{ entry.task.title }}
                </Link>
            </div>
            <dl class="grid gap-1 sm:grid-cols-[10rem_1fr]">
                <template v-for="change in entry.changes" :key="change.field">
                    <dt class="text-muted-foreground">{{ change.label }}</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <span class="text-muted-foreground line-through">
                            {{ change.old ?? "—" }}
                        </span>
                        <ArrowRight class="size-3 text-muted-foreground" />
                        <span class="font-medium">{{ change.new ?? "—" }}</span>
                    </dd>
                </template>
            </dl>
        </li>
    </ul>
</template>
