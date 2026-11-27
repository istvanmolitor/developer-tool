<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from "@inertiajs/vue3";
import { FolderGit2 } from "@lucide/vue";
import { computed, ref, watch } from "vue";
import ChatPanel from "@/components/ChatPanel.vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import type { ChatPromptRun } from "@/components/PromptRunMessage.vue";
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

type AgentContext = {
    id: number;
    project_id: number;
    session_id: string | null;
    created_at: string;
};

type PromptRun = ChatPromptRun & {
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
            <ChatPanel
                v-model="form.prompt"
                :messages="messages"
                :project="selectedProject || null"
                :context="currentContext"
                :processing="form.processing"
                :can-submit="!!selectedProject"
                :can-compose="projects.length > 0"
                :starting-context="startingContext"
                show-context-dividers
                empty-title="Még nem beszélgettél ezzel az agenttel."
                empty-hint="Írj egy feladatot lent — üresen küldve az agent leírása indítja."
                placeholder="Üzenet az agentnek… (Enter: küldés, Shift+Enter: új sor)"
                list-class="max-h-[calc(100vh-22rem)] min-h-0 flex-1"
                @submit="submit"
                @start-new-context="startNewContext"
            >
                <template #message-meta="{ run }">
                    <Link
                        v-if="(run as PromptRun).project"
                        :href="
                            projectRoutes.prompts(
                                (run as PromptRun).project!.name,
                            ).url
                        "
                        class="inline-flex items-center gap-1 hover:underline"
                    >
                        <FolderGit2 class="size-3" />
                        {{ (run as PromptRun).project!.name }}
                    </Link>
                </template>

                <template #selectors>
                    <div class="flex flex-col gap-1 sm:w-56">
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
                    </div>
                </template>

                <template #composer-disabled>
                    <p class="text-sm text-muted-foreground">
                        Még nincs projekt, amin az agent futhatna.
                        <Link
                            :href="projectRoutes.create().url"
                            class="font-medium underline"
                            >Hozz létre egyet.</Link
                        >
                    </p>
                </template>

                <template #errors>
                    <InputError :message="projectError ?? undefined" />
                    <InputError :message="form.errors.prompt" />
                    <InputError :message="form.errors.agent_id" />
                </template>
            </ChatPanel>
        </div>
    </div>
</template>
