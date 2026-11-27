<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3";
import { FolderGit2, MessageSquare } from "@lucide/vue";
import { computed, ref, watch } from "vue";
import AgentSelect, { type AgentOption } from "@/components/AgentSelect.vue";
import ChatPanel from "@/components/ChatPanel.vue";
import InputError from "@/components/InputError.vue";
import type { ChatPromptRun } from "@/components/PromptRunMessage.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { dashboard } from "@/routes";
import agentContexts from "@/routes/agent-contexts";
import projectRoutes from "@/routes/projects";

export type RecentContext = {
    id: number;
    project: string;
    agent: string | null;
    agent_avatar_url: string | null;
    title: string | null;
    last_activity_at: string | null;
};

export type DashboardChatData = {
    projects: { id: number; name: string }[];
    agents: AgentOption[];
    project: string | null;
    agent_id: number | null;
    context: { id: number; session_id: string | null } | null;
    context_chosen: boolean;
    runs: ChatPromptRun[];
    recent_contexts: RecentContext[];
};

const props = defineProps<{
    chat: DashboardChatData;
}>();

const NO_AGENT = "none";

// reka-ui's Select works with string values.
const selectedProject = ref<string>(props.chat.project ?? "");
const selectedAgent = ref<string>(
    props.chat.agent_id === null ? NO_AGENT : String(props.chat.agent_id),
);

/** A conversation chosen from the list decides the project and agent: follow it. */
watch(
    () => [props.chat.project, props.chat.agent_id] as const,
    ([project, agentId]) => {
        selectedProject.value = project ?? "";
        selectedAgent.value = agentId === null ? NO_AGENT : String(agentId);
    },
);

const selectedAgentName = computed(
    () =>
        props.chat.agents.find(
            (agent) => String(agent.id) === selectedAgent.value,
        )?.name ?? null,
);

/** Switch the chat to the latest conversation of the selected project / agent, keeping it in the URL. */
function showSelectedConversation(): void {
    router.get(
        dashboard().url,
        {
            project: selectedProject.value || undefined,
            agent:
                selectedAgent.value === NO_AGENT
                    ? undefined
                    : selectedAgent.value,
        },
        {
            only: ["chat"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onProjectChange(project: unknown): void {
    selectedProject.value = String(project ?? "");
    showSelectedConversation();
}

function onAgentChange(agent: unknown): void {
    selectedAgent.value = String(agent ?? NO_AGENT);
    showSelectedConversation();
}

const form = useForm({
    prompt: "",
    agent_id: null as number | null,
    agent_context_id: null as number | null,
});

function submit(): void {
    if (form.processing || !selectedProject.value) {
        return;
    }

    form.agent_id =
        selectedAgent.value === NO_AGENT ? null : Number(selectedAgent.value);
    // A conversation chosen from the list goes on, not the latest one.
    form.agent_context_id = props.chat.context_chosen
        ? (props.chat.context?.id ?? null)
        : null;

    form.post(projectRoutes.runs.store(selectedProject.value).url, {
        preserveScroll: true,
        onSuccess: () => form.reset("prompt"),
    });
}

const startingContext = ref(false);

/** Start a new conversation (fresh claude session) from the current context. */
function startNewContext(): void {
    if (props.chat.context === null || startingContext.value) {
        return;
    }

    router.post(
        agentContexts.store(props.chat.context.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (startingContext.value = true),
            onFinish: () => (startingContext.value = false),
            // The new conversation is the latest one: leave the chosen one.
            onSuccess: () => {
                if (props.chat.context_chosen) {
                    showSelectedConversation();
                }
            },
        },
    );
}
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0 xl:min-h-0">
        <CardHeader class="gap-0 border-b py-3 [.border-b]:pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <MessageSquare class="size-4" />
                Chat
            </CardTitle>
        </CardHeader>

        <CardContent class="flex flex-col p-0 xl:min-h-0 xl:flex-1">
            <ChatPanel
                v-model="form.prompt"
                :messages="chat.runs"
                :project="selectedProject || null"
                :context="chat.context"
                :processing="form.processing"
                :can-submit="!!selectedProject"
                :can-compose="chat.projects.length > 0"
                :starting-context="startingContext"
                empty-title="Még nincs üzenet ebben a beszélgetésben."
                empty-hint="Válassz projektet és agentet, majd írj lent."
                :placeholder="
                    selectedAgentName
                        ? 'Üzenet az agentnek… (üresen az agent leírása indítja; Enter: küldés)'
                        : 'Üzenet a claude-nak… (Enter: küldés, Shift+Enter: új sor)'
                "
                list-class="h-[28rem] xl:h-auto xl:min-h-0 xl:flex-1"
                @submit="submit"
                @start-new-context="startNewContext"
            >
                <template #selectors>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <AgentSelect
                            :model-value="selectedAgent"
                            :agents="chat.agents"
                            :none-value="NO_AGENT"
                            none-label="Nincs — sima claude"
                            trigger-class="w-full flex-1"
                            @update:model-value="onAgentChange"
                        />
                        <Select
                            :model-value="selectedProject"
                            @update:model-value="onProjectChange"
                        >
                            <SelectTrigger
                                class="w-full flex-1"
                                aria-label="Projekt"
                            >
                                <SelectValue placeholder="Válassz projektet…">
                                    <span class="flex items-center gap-2">
                                        <FolderGit2 class="size-4" />
                                        {{ selectedProject }}
                                    </span>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="project in chat.projects"
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
                        Még nincs projekt, amin a claude futhatna.
                        <Link
                            :href="projectRoutes.create().url"
                            class="font-medium underline"
                            >Hozz létre egyet.</Link
                        >
                    </p>
                </template>

                <template #errors>
                    <InputError :message="form.errors.prompt" />
                    <InputError :message="form.errors.agent_id" />
                </template>
            </ChatPanel>
        </CardContent>
    </Card>
</template>
