<script setup lang="ts">
import { Head, Link, usePoll } from "@inertiajs/vue3";
import { Bot, Clock, ListOrdered, Loader2 } from "@lucide/vue";
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { dashboard } from "@/routes";
import projects from "@/routes/projects";

type QueuedRun = {
    id: number;
    prompt: string;
    started_at: string | null;
    created_at: string;
    project: { id: number; name: string } | null;
    agent: { id: number; name: string } | null;
};

defineProps<{
    promptQueue: {
        running: QueuedRun[];
        queued: QueuedRun[];
        queued_count: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: "Vezérlőpult",
                href: dashboard(),
            },
        ],
    },
});

usePoll(4000, { only: ["promptQueue"] });

// Ticks every second so the elapsed time of a running prompt counts up live.
const now = ref(Date.now());
let ticker: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    ticker = setInterval(() => (now.value = Date.now()), 1000);
});

onBeforeUnmount(() => clearInterval(ticker));

function elapsed(since: string | null): string {
    if (since === null) {
        return "—";
    }

    const seconds = Math.max(
        0,
        Math.floor((now.value - new Date(since).getTime()) / 1000),
    );
    const minutes = Math.floor(seconds / 60);

    return minutes > 0 ? `${minutes} perc ${seconds % 60} mp` : `${seconds} mp`;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString("hu-HU");
}
</script>

<template>
    <Head title="Vezérlőpult" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Loader2
                            class="size-4"
                            :class="
                                promptQueue.running.length > 0
                                    ? 'animate-spin'
                                    : 'text-muted-foreground'
                            "
                        />
                        Éppen futó prompt
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p
                        v-if="promptQueue.running.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Jelenleg nem fut prompt.
                    </p>

                    <div
                        v-for="run in promptQueue.running"
                        :key="run.id"
                        class="flex flex-col gap-1.5 rounded-lg border p-3"
                    >
                        <p
                            class="line-clamp-3 text-sm font-medium break-words whitespace-pre-wrap"
                        >
                            {{ run.prompt }}
                        </p>
                        <p
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                        >
                            <Link
                                v-if="run.project"
                                :href="projects.prompts(run.project.name).url"
                                class="font-medium text-foreground hover:underline"
                            >
                                {{ run.project.name }}
                            </Link>
                            <Badge
                                v-if="run.agent"
                                variant="outline"
                                class="gap-1 font-normal"
                            >
                                <Bot class="size-3" />
                                {{ run.agent.name }}
                            </Badge>
                            <span class="inline-flex items-center gap-1">
                                <Clock class="size-3" />
                                {{ elapsed(run.started_at) }}
                            </span>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ListOrdered class="size-4" />
                        Hátralévő promptok
                        <Badge variant="secondary">
                            {{ promptQueue.queued_count }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-2">
                    <p
                        v-if="promptQueue.queued_count === 0"
                        class="text-sm text-muted-foreground"
                    >
                        A sor üres.
                    </p>

                    <ol v-else class="flex flex-col gap-2">
                        <li
                            v-for="(run, index) in promptQueue.queued"
                            :key="run.id"
                            class="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <span
                                class="w-5 shrink-0 text-right text-xs font-medium text-muted-foreground"
                            >
                                {{ index + 1 }}.
                            </span>
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <p class="truncate text-sm">
                                    {{ run.prompt }}
                                </p>
                                <p
                                    class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                                >
                                    <Link
                                        v-if="run.project"
                                        :href="
                                            projects.prompts(run.project.name)
                                                .url
                                        "
                                        class="hover:underline"
                                    >
                                        {{ run.project.name }}
                                    </Link>
                                    <span v-if="run.agent">
                                        · {{ run.agent.name }}
                                    </span>
                                    <span
                                        >·
                                        {{ formatDate(run.created_at) }}</span
                                    >
                                </p>
                            </div>
                        </li>
                    </ol>

                    <p
                        v-if="
                            promptQueue.queued_count > promptQueue.queued.length
                        "
                        class="text-xs text-muted-foreground"
                    >
                        …és még
                        {{
                            promptQueue.queued_count - promptQueue.queued.length
                        }}
                        prompt.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
