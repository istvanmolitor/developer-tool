<script setup lang="ts">
import { Head, useForm, usePoll } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Loader2,
    Send,
    XCircle,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import projects from '@/routes/projects';

type PromptRunStatus = 'pending' | 'running' | 'completed' | 'failed';

type PromptRun = {
    id: number;
    project: string;
    prompt: string;
    status: PromptRunStatus;
    exit_code: number | null;
    output: string | null;
    error_output: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
};

type Project = {
    name: string;
    path: string;
    last_modified_at: string | null;
    is_git_repository: boolean;
};

const props = defineProps<{
    project: Project;
    runs: PromptRun[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projects.index().url }],
    },
});

const form = useForm({ prompt: '' });

function submit(): void {
    form.post(projects.runs.store(props.project.name).url, {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt'),
    });
}

const hasActiveRun = computed(() =>
    props.runs.some((run) => run.status === 'pending' || run.status === 'running'),
);

const { start, stop } = usePoll(
    4000,
    { only: ['runs'] },
    { autoStart: false },
);

watch(
    hasActiveRun,
    (active) => (active ? start() : stop()),
    { immediate: true },
);

const statusMeta: Record<PromptRunStatus, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: unknown }> = {
    pending: { label: 'Pending', variant: 'secondary', icon: Clock },
    running: { label: 'Running', variant: 'default', icon: Loader2 },
    completed: { label: 'Completed', variant: 'default', icon: CheckCircle2 },
    failed: { label: 'Failed', variant: 'destructive', icon: XCircle },
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head :title="`${project.name} — Projects`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading :title="project.name" :description="project.path" />

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Run a claude prompt</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <textarea
                        v-model="form.prompt"
                        rows="4"
                        placeholder="Describe what claude should change in this project…"
                        class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="form.errors.prompt" />
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted-foreground">
                            Runs <code>claude -p</code> inside the project directory.
                        </p>
                        <Button type="submit" :disabled="form.processing">
                            <Send class="size-4" />
                            {{ form.processing ? 'Queuing…' : 'Run prompt' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">
                History
                <span v-if="hasActiveRun" class="ml-1 inline-flex items-center gap-1">
                    <Loader2 class="size-3 animate-spin" /> live
                </span>
            </h2>

            <p v-if="runs.length === 0" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                No prompts have been run against this project yet.
            </p>

            <Card v-for="run in runs" :key="run.id">
                <CardHeader>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm font-medium">{{ run.prompt }}</p>
                        <Badge :variant="statusMeta[run.status].variant" class="gap-1">
                            <component
                                :is="statusMeta[run.status].icon"
                                class="size-3"
                                :class="run.status === 'running' ? 'animate-spin' : ''"
                            />
                            {{ statusMeta[run.status].label }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(run.created_at) }}
                        <span v-if="run.exit_code !== null"> · exit {{ run.exit_code }}</span>
                    </p>
                </CardHeader>
                <CardContent v-if="run.output || run.error_output" class="flex flex-col gap-2">
                    <pre
                        v-if="run.output"
                        class="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap break-words"
                    >{{ run.output }}</pre>
                    <pre
                        v-if="run.error_output"
                        class="max-h-80 overflow-auto rounded-md bg-destructive/10 p-3 text-xs whitespace-pre-wrap break-words text-destructive"
                    >{{ run.error_output }}</pre>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
