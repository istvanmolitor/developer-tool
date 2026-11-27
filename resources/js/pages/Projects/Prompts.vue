<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from "@inertiajs/vue3";
import { Bot, Pencil, Trash2, TriangleAlert } from "@lucide/vue";
import { computed, ref, watch } from "vue";
import ChatPanel from "@/components/ChatPanel.vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import type { ChatPromptRun } from "@/components/PromptRunMessage.vue";
import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import agentContexts from "@/routes/agent-contexts";
import projects from "@/routes/projects";
import ProjectNav from "./ProjectNav.vue";

type Agent = { id: number; name: string };

type Project = {
    id: number;
    name: string;
    path: string;
    description: string | null;
    exists: boolean;
    is_git_repository: boolean;
};

type AgentContext = {
    id: number;
    agent_id: number | null;
    session_id: string | null;
    created_at: string;
};

type PromptRun = ChatPromptRun & {
    agent: Agent | null;
};

const props = defineProps<{
    project: Project;
    agents: Agent[];
    runs: PromptRun[];
    contexts: AgentContext[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const NO_AGENT = "none";

/** The runs arrive newest first; a chat reads oldest to newest. */
const messages = computed(() => [...props.runs].reverse());

const selectedAgent = ref<string>(NO_AGENT);

const selectedAgentName = computed(
    () =>
        props.agents.find((agent) => String(agent.id) === selectedAgent.value)
            ?.name ?? null,
);

const form = useForm({ prompt: "", agent_id: null as number | null });

/** The context the chat continues for the selected agent, if it has one yet. */
const currentContext = computed(() => {
    const agentId =
        selectedAgent.value === NO_AGENT ? null : Number(selectedAgent.value);

    return (
        props.contexts.find((context) => context.agent_id === agentId) ?? null
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

function submit(): void {
    if (form.processing) {
        return;
    }

    form.agent_id =
        selectedAgent.value === NO_AGENT ? null : Number(selectedAgent.value);

    form.post(projects.runs.store(props.project.name).url, {
        preserveScroll: true,
        onSuccess: () => form.reset("prompt"),
    });
}

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
    <Head :title="`Chat — ${project.name}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="project.name"
                description="Promptok futtatása a projekten."
            />
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

        <div
            class="flex min-h-[28rem] flex-1 flex-col overflow-hidden rounded-xl border"
        >
            <ChatPanel
                v-model="form.prompt"
                :messages="messages"
                :project="project.name"
                :context="currentContext"
                :processing="form.processing"
                :starting-context="startingContext"
                show-context-dividers
                empty-title="Ezen a projekten még nem futott egyetlen prompt sem."
                empty-hint="Válassz agentet (vagy hagyd sima claude-on), és írj lent."
                :placeholder="
                    selectedAgentName
                        ? 'Üzenet az agentnek… (üresen az agent leírása indítja; Enter: küldés)'
                        : 'Írd le, mit változtasson a claude ezen a projekten… (Enter: küldés, Shift+Enter: új sor)'
                "
                list-class="max-h-[calc(100vh-24rem)] min-h-0 flex-1"
                @submit="submit"
                @start-new-context="startNewContext"
            >
                <template #message-meta="{ run }">
                    <span
                        v-if="(run as PromptRun).agent"
                        class="inline-flex items-center gap-1"
                    >
                        <Bot class="size-3" />
                        {{ (run as PromptRun).agent!.name }}
                    </span>
                </template>

                <template #selectors>
                    <div class="flex flex-col gap-1 sm:w-64">
                        <label
                            class="text-xs font-medium text-muted-foreground"
                            for="agent-select"
                        >
                            Agent
                        </label>
                        <Select v-model="selectedAgent">
                            <SelectTrigger id="agent-select" class="w-full">
                                <SelectValue>
                                    <span class="flex items-center gap-2">
                                        <Bot class="size-4" />
                                        {{
                                            selectedAgentName ??
                                            "Nincs — sima claude"
                                        }}
                                    </span>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_AGENT">
                                    Nincs — sima claude
                                </SelectItem>
                                <SelectItem
                                    v-for="agent in agents"
                                    :key="agent.id"
                                    :value="String(agent.id)"
                                >
                                    {{ agent.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </template>

                <template #errors>
                    <InputError :message="form.errors.prompt" />
                    <InputError :message="form.errors.agent_id" />
                </template>
            </ChatPanel>
        </div>
    </div>
</template>
