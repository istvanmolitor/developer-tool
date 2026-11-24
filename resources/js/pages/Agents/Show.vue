<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from "@inertiajs/vue3";
import {
    CheckCircle2,
    ChevronDown,
    Clock,
    FolderGit2,
    Loader2,
    Pencil,
    Send,
    Trash2,
    XCircle,
} from "@lucide/vue";
import { computed, ref, watch } from "vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import agents from "@/routes/agents";
import projectRoutes from "@/routes/projects";

type Agent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model: string | null;
    tools: string[] | null;
    system_prompt: string;
    source_path: string | null;
};

type Project = {
    id: number;
    name: string;
};

type PromptRunStatus = "pending" | "running" | "completed" | "failed";

type PromptRun = {
    id: number;
    prompt: string;
    status: PromptRunStatus;
    exit_code: number | null;
    output: string | null;
    error_output: string | null;
    created_at: string;
    project: Project | null;
};

const props = defineProps<{
    agent: Agent;
    projects: Project[];
    runs: PromptRun[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Agentek", href: agents.index().url }],
    },
});

/** Descriptions longer than this start collapsed behind a toggle. */
const DESCRIPTION_COLLAPSE_LENGTH = 160;

const descriptionExpanded = ref(false);

const isDescriptionLong = computed(
    () => (props.agent.description ?? "").length > DESCRIPTION_COLLAPSE_LENGTH,
);

const selectedProject = ref<string>(
    props.projects.length === 1 ? props.projects[0].name : "",
);

const form = useForm({ prompt: "", agent_id: props.agent.id });

const projectError = ref<string | null>(null);

function submit(): void {
    if (!selectedProject.value) {
        projectError.value = "Válassz projektet.";

        return;
    }

    projectError.value = null;

    form.post(projectRoutes.runs.store(selectedProject.value).url, {
        preserveScroll: true,
        onSuccess: () => form.reset("prompt"),
    });
}

function destroy(): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${props.agent.name}” agentet? Ez a művelet nem vonható vissza.`,
        )
    ) {
        return;
    }

    router.delete(agents.destroy(props.agent.id).url);
}

const hasActiveRun = computed(() =>
    props.runs.some(
        (run) => run.status === "pending" || run.status === "running",
    ),
);

const { start, stop } = usePoll(4000, { only: ["runs"] }, { autoStart: false });

watch(hasActiveRun, (active) => (active ? start() : stop()), {
    immediate: true,
});

const statusMeta: Record<
    PromptRunStatus,
    {
        label: string;
        variant: "default" | "secondary" | "destructive" | "outline";
        icon: unknown;
    }
> = {
    pending: { label: "Függőben", variant: "secondary", icon: Clock },
    running: { label: "Fut", variant: "default", icon: Loader2 },
    completed: { label: "Kész", variant: "default", icon: CheckCircle2 },
    failed: { label: "Sikertelen", variant: "destructive", icon: XCircle },
};

function formatDate(value: string): string {
    return new Date(value).toLocaleString("hu-HU");
}
</script>

<template>
    <Head :title="`${agent.name} — Agentek`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <Heading :title="agent.name" variant="small" />
                <p class="font-mono text-xs text-muted-foreground">
                    {{ agent.slug }}
                </p>
                <template v-if="agent.description">
                    <p
                        class="text-sm whitespace-pre-line text-muted-foreground"
                        :class="{
                            'line-clamp-2':
                                isDescriptionLong && !descriptionExpanded,
                        }"
                    >
                        {{ agent.description }}
                    </p>
                    <button
                        v-if="isDescriptionLong"
                        type="button"
                        class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                        :aria-expanded="descriptionExpanded"
                        @click="descriptionExpanded = !descriptionExpanded"
                    >
                        {{ descriptionExpanded ? "Kevesebb" : "Továbbiak" }}
                        <ChevronDown
                            class="size-3.5 transition-transform"
                            :class="{ 'rotate-180': descriptionExpanded }"
                        />
                    </button>
                </template>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="agents.edit(agent.id).url">
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

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Prompt az agentnek</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="projects.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Még nincs projekt, amin az agent futhatna.
                    <Link
                        :href="projectRoutes.create().url"
                        class="font-medium underline"
                        >Hozz létre egyet.</Link
                    >
                </p>
                <form
                    v-else
                    class="flex flex-col gap-3"
                    @submit.prevent="submit"
                >
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-xs font-medium text-muted-foreground"
                            for="project-select"
                        >
                            Projekt
                        </label>
                        <Select v-model="selectedProject">
                            <SelectTrigger id="project-select" class="w-full">
                                <SelectValue placeholder="Válassz projektet…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.name"
                                >
                                    {{ project.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="projectError ?? undefined" />
                    </div>

                    <textarea
                        v-model="form.prompt"
                        rows="4"
                        placeholder="Feladat az agentnek (opcionális — üresen hagyva az agent leírása indítja)…"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                    />
                    <InputError :message="form.errors.prompt" />
                    <InputError :message="form.errors.agent_id" />
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            <Send class="size-4" />
                            {{
                                form.processing
                                    ? "Sorba állítás…"
                                    : "Prompt futtatása"
                            }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div v-if="runs.length > 0" class="flex flex-col gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">
                Legutóbbi futtatások
                <span
                    v-if="hasActiveRun"
                    class="ml-1 inline-flex items-center gap-1"
                >
                    <Loader2 class="size-3 animate-spin" /> élő
                </span>
            </h2>

            <Card v-for="run in runs" :key="run.id">
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <p
                            class="line-clamp-3 min-w-0 flex-1 text-sm font-medium break-words whitespace-pre-wrap"
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
                    <p
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span>{{ formatDate(run.created_at) }}</span>
                        <Link
                            v-if="run.project"
                            :href="projectRoutes.prompts(run.project.name).url"
                            class="inline-flex items-center gap-1 hover:underline"
                        >
                            <FolderGit2 class="size-3" />
                            {{ run.project.name }}
                        </Link>
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

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Részletek</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge v-if="agent.model" variant="secondary">{{
                        agent.model
                    }}</Badge>
                    <Badge
                        v-for="tool in agent.tools ?? []"
                        :key="tool"
                        variant="outline"
                        >{{ tool }}</Badge
                    >
                    <span
                        v-if="!agent.model && !(agent.tools ?? []).length"
                        class="text-xs text-muted-foreground"
                        >Nincs modell/eszköz megadva — öröklött
                        alapértelmezés.</span
                    >
                </div>

                <div v-if="agent.source_path">
                    <p class="text-xs font-medium text-muted-foreground">
                        Forrás
                    </p>
                    <p class="mt-1 font-mono break-all">
                        {{ agent.source_path }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        System prompt
                    </p>
                    <pre
                        class="mt-1 max-h-[32rem] overflow-auto rounded-md border bg-muted/40 p-3 font-mono text-xs whitespace-pre-wrap"
                        >{{ agent.system_prompt }}</pre>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
