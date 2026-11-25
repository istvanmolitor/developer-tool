<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from "@inertiajs/vue3";
import {
    Bot,
    CheckCircle2,
    Clock,
    FolderGit2,
    Loader2,
    MessageSquare,
    MessageSquarePlus,
    Send,
    XCircle,
} from "@lucide/vue";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import agentContexts from "@/routes/agent-contexts";
import agents from "@/routes/agents";
import projectRoutes from "@/routes/projects";
import AgentNav from "./AgentNav.vue";

type Agent = {
    id: number;
    name: string;
    slug: string;
};

type Project = {
    id: number;
    name: string;
};

type PromptRunStatus = "pending" | "running" | "completed" | "failed";

type AgentContext = {
    id: number;
    project_id: number;
    session_id: string | null;
    created_at: string;
};

type PromptRun = {
    id: number;
    agent_context_id: number | null;
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
    contexts: AgentContext[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Agentek", href: agents.index().url }],
    },
});

/** The runs arrive newest first; a chat reads oldest to newest. */
const messages = computed(() => [...props.runs].reverse());

/** Whether a run opens a new conversation (context) compared to the run before it. */
function startsNewContext(index: number): boolean {
    return (
        index > 0 &&
        messages.value[index].agent_context_id !==
            messages.value[index - 1].agent_context_id
    );
}

const selectedProject = ref<string>(
    props.projects.length === 1 ? props.projects[0].name : "",
);

const form = useForm({ prompt: "", agent_id: props.agent.id });

/** The context the chat continues in the selected project, if it has one yet. */
const currentContext = computed(() => {
    const project = props.projects.find(
        (project) => project.name === selectedProject.value,
    );

    return (
        props.contexts.find((context) => context.project_id === project?.id) ??
        null
    );
});

const startingContext = ref(false);

/** Start a new conversation (fresh claude session) from the current context. */
function startNewContext(): void {
    if (currentContext.value === null || startingContext.value) {
        return;
    }

    router.post(
        agentContexts.store(currentContext.value.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (startingContext.value = true),
            onFinish: () => (startingContext.value = false),
        },
    );
}

const projectError = ref<string | null>(null);

function submit(): void {
    if (form.processing) {
        return;
    }

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

/** Enter sends the message, Shift+Enter adds a new line. */
function onPromptKeydown(event: KeyboardEvent): void {
    if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        submit();
    }
}

const hasActiveRun = computed(() =>
    props.runs.some(
        (run) => run.status === "pending" || run.status === "running",
    ),
);

const { start, stop } = usePoll(
    4000,
    { only: ["runs", "contexts"] },
    { autoStart: false },
);

watch(hasActiveRun, (active) => (active ? start() : stop()), {
    immediate: true,
});

const messageList = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    nextTick(() => {
        messageList.value?.scrollTo({ top: messageList.value.scrollHeight });
    });
}

onMounted(scrollToBottom);

watch(
    () => props.runs.map((run) => `${run.id}:${run.status}`).join(","),
    scrollToBottom,
);

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

    <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4">
        <div>
            <Heading :title="agent.name" variant="small" />
            <p class="font-mono text-xs text-muted-foreground">
                {{ agent.slug }}
            </p>
        </div>

        <AgentNav :agent-id="agent.id" />

        <div
            class="flex min-h-[28rem] flex-1 flex-col overflow-hidden rounded-xl border"
        >
            <div
                ref="messageList"
                class="flex max-h-[calc(100vh-22rem)] min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4"
            >
                <div
                    v-if="messages.length === 0"
                    class="m-auto flex flex-col items-center gap-2 text-center text-sm text-muted-foreground"
                >
                    <MessageSquare class="size-8" />
                    <p>Még nem beszélgettél ezzel az agenttel.</p>
                    <p class="text-xs">
                        Írj egy feladatot lent — üresen küldve az agent
                        leírása indítja.
                    </p>
                </div>

                <template v-for="(run, index) in messages" :key="run.id">
                    <div
                        v-if="startsNewContext(index)"
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <div class="h-px flex-1 bg-border" />
                        Új beszélgetés
                        <div class="h-px flex-1 bg-border" />
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <div
                            class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm break-words whitespace-pre-wrap text-primary-foreground"
                        >
                            <template v-if="run.prompt">{{
                                run.prompt
                            }}</template>
                            <span v-else class="italic opacity-80"
                                >(az agent leírása alapján)</span
                            >
                        </div>
                        <p
                            class="flex flex-wrap items-center justify-end gap-x-2 text-xs text-muted-foreground"
                        >
                            <Link
                                v-if="run.project"
                                :href="
                                    projectRoutes.prompts(run.project.name).url
                                "
                                class="inline-flex items-center gap-1 hover:underline"
                            >
                                <FolderGit2 class="size-3" />
                                {{ run.project.name }}
                            </Link>
                            <span>{{ formatDate(run.created_at) }}</span>
                        </p>
                    </div>

                    <div class="flex items-start gap-2">
                        <div
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted"
                        >
                            <Bot class="size-4 text-muted-foreground" />
                        </div>
                        <div class="flex max-w-[85%] min-w-0 flex-col gap-1">
                            <div
                                class="flex flex-col gap-2 rounded-2xl rounded-tl-sm bg-muted px-4 py-2 text-sm"
                            >
                                <p
                                    v-if="
                                        run.status === 'pending' ||
                                        run.status === 'running'
                                    "
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <Loader2 class="size-4 animate-spin" />
                                    {{
                                        run.status === "pending"
                                            ? "Sorban áll…"
                                            : "Dolgozik…"
                                    }}
                                </p>
                                <pre
                                    v-if="run.output"
                                    class="font-sans break-words whitespace-pre-wrap"
                                    >{{ run.output }}</pre>
                                <pre
                                    v-if="run.error_output"
                                    class="max-h-60 overflow-auto rounded-md bg-destructive/10 p-2 text-xs break-words whitespace-pre-wrap text-destructive"
                                    >{{ run.error_output }}</pre>
                                <p
                                    v-if="
                                        !run.output &&
                                        !run.error_output &&
                                        (run.status === 'completed' ||
                                            run.status === 'failed')
                                    "
                                    class="text-muted-foreground italic"
                                >
                                    Nincs kimenet.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
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
                                <span
                                    v-if="run.exit_code !== null"
                                    class="text-xs text-muted-foreground"
                                    >kilépési kód {{ run.exit_code }}</span
                                >
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="border-t bg-background p-3">
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
                    class="flex flex-col gap-2"
                    @submit.prevent="submit"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="flex flex-col gap-1 sm:w-56">
                            <label
                                class="text-xs font-medium text-muted-foreground"
                                for="project-select"
                            >
                                Projekt
                            </label>
                            <Select v-model="selectedProject">
                                <SelectTrigger
                                    id="project-select"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Válassz projektet…"
                                    />
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
                        </div>
                        <textarea
                            v-model="form.prompt"
                            rows="2"
                            aria-label="Üzenet az agentnek"
                            placeholder="Üzenet az agentnek… (Enter: küldés, Shift+Enter: új sor)"
                            class="min-h-9 w-full flex-1 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                            @keydown="onPromptKeydown"
                        />
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            aria-label="Küldés"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="size-4 animate-spin"
                            />
                            <Send v-else class="size-4" />
                            Küldés
                        </Button>
                    </div>
                    <div
                        v-if="currentContext"
                        class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                    >
                        <span>
                            {{
                                currentContext.session_id
                                    ? "Az üzenetek a korábbi beszélgetést folytatják."
                                    : "Új beszélgetés — az első üzenet indítja."
                            }}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="
                                startingContext || !currentContext.session_id
                            "
                            @click="startNewContext"
                        >
                            <MessageSquarePlus class="size-4" />
                            Új beszélgetés
                        </Button>
                    </div>
                    <InputError :message="projectError ?? undefined" />
                    <InputError :message="form.errors.prompt" />
                    <InputError :message="form.errors.agent_id" />
                </form>
            </div>
        </div>
    </div>
</template>
