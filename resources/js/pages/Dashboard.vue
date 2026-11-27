<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { Clock, Info, ListTodo, Loader2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import DashboardChat from '@/components/DashboardChat.vue';
import type { DashboardChatData } from '@/components/DashboardChat.vue';
import DashboardChatContexts from '@/components/DashboardChatContexts.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import tasks from '@/routes/tasks';

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
    chat: DashboardChatData;
    taskStats: {
        total: number;
        in_progress: number;
    };
}>();

const selectedRun = ref<QueuedRun | null>(null);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Vezérlőpult',
                href: dashboard(),
            },
        ],
    },
});

usePoll(4000, { only: ['promptQueue', 'chat'] });

// Ticks every second so the elapsed time of a running prompt counts up live.
const now = ref(Date.now());
let ticker: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    ticker = setInterval(() => (now.value = Date.now()), 1000);
});

onBeforeUnmount(() => clearInterval(ticker));

function elapsed(since: string | null): string {
    if (since === null) {
        return '—';
    }

    const seconds = Math.max(
        0,
        Math.floor((now.value - new Date(since).getTime()) / 1000),
    );
    const minutes = Math.floor(seconds / 60);

    return minutes > 0 ? `${minutes} perc ${seconds % 60} mp` : `${seconds} mp`;
}
</script>

<template>
    <Head title="Vezérlőpult" />

    <div
        class="grid h-full flex-1 items-start gap-4 overflow-x-auto p-4 lg:grid-cols-[16rem_minmax(0,1fr)] xl:h-[calc(100svh-5rem)] xl:flex-none xl:grid-cols-[16rem_minmax(0,1fr)_24rem] xl:grid-rows-[minmax(0,1fr)] xl:items-stretch xl:group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(100svh-4rem)]"
    >
        <DashboardChatContexts
            :contexts="chat.recent_contexts"
            :active-context-id="chat.context?.id ?? null"
        />

        <DashboardChat :chat="chat" />

        <div
            class="flex min-w-0 flex-col gap-4 lg:col-span-2 xl:col-span-1 xl:min-h-0 xl:overflow-y-auto"
        >
            <Card>
                <CardContent class="flex flex-col gap-2">
                    <div
                        v-if="promptQueue.running.length === 0"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Loader2 class="size-4 shrink-0" />
                        Jelenleg nem fut prompt.
                    </div>

                    <div
                        v-for="run in promptQueue.running"
                        :key="run.id"
                        class="flex items-center gap-2"
                    >
                        <Loader2
                            class="size-4 shrink-0 animate-spin text-ai-foreground"
                        />
                        <span
                            class="min-w-0 flex-1 truncate text-sm font-medium"
                        >
                            {{ run.prompt }}
                        </span>
                        <span
                            class="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Clock class="size-3" />
                            {{ elapsed(run.started_at) }}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0"
                            title="Prompt részletei"
                            @click="selectedRun = run"
                        >
                            <Info class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ListTodo class="size-4" />
                        <Link :href="tasks.index().url" class="hover:underline">
                            Feladatok
                        </Link>
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex gap-6">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">
                            Összes
                        </span>
                        <span class="text-2xl leading-none font-bold">
                            {{ taskStats.total }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">
                            Folyamatban
                        </span>
                        <span
                            class="text-2xl leading-none font-bold text-ai-foreground"
                        >
                            {{ taskStats.in_progress }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <Dialog
        :open="selectedRun !== null"
        @update:open="(open) => !open && (selectedRun = null)"
    >
        <DialogContent class="max-h-[85vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Futó prompt</DialogTitle>
                <DialogDescription>
                    {{ selectedRun?.project?.name ?? '—' }} ·
                    {{ selectedRun?.agent?.name ?? 'nincs agent' }} ·
                    {{ elapsed(selectedRun?.started_at ?? null) }}
                </DialogDescription>
            </DialogHeader>
            <pre
                class="rounded-md bg-muted p-3 text-xs break-words whitespace-pre-wrap"
                >{{ selectedRun?.prompt }}</pre>
        </DialogContent>
    </Dialog>
</template>
