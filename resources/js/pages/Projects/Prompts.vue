<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Loader2,
    Pencil,
    Send,
    Trash2,
    TriangleAlert,
    XCircle,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import projects from '@/routes/projects';
import ProjectNav from './ProjectNav.vue';

type PromptRunStatus = 'pending' | 'running' | 'completed' | 'failed';

type PromptRun = {
    id: number;
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
    id: number;
    name: string;
    path: string;
    description: string | null;
    exists: boolean;
    is_git_repository: boolean;
    last_modified_at: string | null;
};

const props = defineProps<{
    project: Project;
    runs: PromptRun[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projects.index().url }],
    },
});

const form = useForm({ prompt: '' });

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

function submit(): void {
    form.post(projects.runs.store(props.project.name).url, {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt'),
    });
}

const hasActiveRun = computed(() =>
    props.runs.some(
        (run) => run.status === 'pending' || run.status === 'running',
    ),
);

const { start, stop } = usePoll(4000, { only: ['runs'] }, { autoStart: false });

watch(hasActiveRun, (active) => (active ? start() : stop()), {
    immediate: true,
});

const statusMeta: Record<
    PromptRunStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
        icon: unknown;
    }
> = {
    pending: { label: 'Függőben', variant: 'secondary', icon: Clock },
    running: { label: 'Fut', variant: 'default', icon: Loader2 },
    completed: { label: 'Kész', variant: 'default', icon: CheckCircle2 },
    failed: { label: 'Sikertelen', variant: 'destructive', icon: XCircle },
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head :title="`Promptok — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="project.name" :description="project.path" />
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="projects.edit(project.name).url">
                        <Pencil class="size-4" />
                        Szerkesztés
                    </Link>
                </Button>
                <Button
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

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Claude prompt futtatása</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <textarea
                        v-model="form.prompt"
                        rows="4"
                        placeholder="Írd le, mit változtasson a claude ezen a projekten…"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                    />
                    <InputError :message="form.errors.prompt" />
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted-foreground">
                            A <code>claude -p</code> parancsot futtatja a projekt
                            könyvtárában.
                        </p>
                        <Button type="submit" :disabled="form.processing">
                            <Send class="size-4" />
                            {{ form.processing ? 'Sorba állítás…' : 'Prompt futtatása' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">
                Előzmények
                <span
                    v-if="hasActiveRun"
                    class="ml-1 inline-flex items-center gap-1"
                >
                    <Loader2 class="size-3 animate-spin" /> élő
                </span>
            </h2>

            <p
                v-if="runs.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Ezen a projekten még nem futott egyetlen prompt sem.
            </p>

            <Card v-for="run in runs" :key="run.id">
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <p
                            class="min-w-0 flex-1 text-sm font-medium break-words whitespace-pre-wrap"
                        >
                            {{ run.prompt }}
                        </p>
                        <Badge
                            :variant="statusMeta[run.status].variant"
                            class="gap-1"
                        >
                            <component
                                :is="statusMeta[run.status].icon"
                                class="size-3"
                                :class="
                                    run.status === 'running'
                                        ? 'animate-spin'
                                        : ''
                                "
                            />
                            {{ statusMeta[run.status].label }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(run.created_at) }}
                        <span v-if="run.exit_code !== null">
                            · kilépési kód {{ run.exit_code }}</span
                        >
                    </p>
                </CardHeader>
                <CardContent
                    v-if="run.output || run.error_output"
                    class="flex flex-col gap-2"
                >
                    <pre
                        v-if="run.output"
                        class="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs break-words whitespace-pre-wrap"
                        >{{ run.output }}</pre>
                    <pre
                        v-if="run.error_output"
                        class="max-h-80 overflow-auto rounded-md bg-destructive/10 p-3 text-xs break-words whitespace-pre-wrap text-destructive"
                        >{{ run.error_output }}</pre>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
