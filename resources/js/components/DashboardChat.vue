<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3";
import {
    Bot,
    FolderGit2,
    Loader2,
    MessageSquare,
    MessageSquarePlus,
    Send,
} from "@lucide/vue";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import InputError from "@/components/InputError.vue";
import PromptRunMessage from "@/components/PromptRunMessage.vue";
import type { ChatPromptRun } from "@/components/PromptRunMessage.vue";
import { Button } from "@/components/ui/button";
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

export type DashboardChatData = {
    projects: { id: number; name: string }[];
    agents: { id: number; name: string }[];
    project: string | null;
    agent_id: number | null;
    context: { id: number; session_id: string | null } | null;
    runs: ChatPromptRun[];
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

const selectedAgentName = computed(
    () =>
        props.chat.agents.find(
            (agent) => String(agent.id) === selectedAgent.value,
        )?.name ?? null,
);

/** Switch the chat to another project / agent conversation, keeping it in the URL. */
watch([selectedProject, selectedAgent], ([project, agent]) => {
    router.get(
        dashboard().url,
        {
            project: project || undefined,
            agent: agent === NO_AGENT ? undefined : agent,
        },
        {
            only: ["chat"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
});

const form = useForm({ prompt: "", agent_id: null as number | null });

function submit(): void {
    if (form.processing || !selectedProject.value) {
        return;
    }

    form.agent_id =
        selectedAgent.value === NO_AGENT ? null : Number(selectedAgent.value);

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
        },
    );
}

const messageList = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    nextTick(() => {
        messageList.value?.scrollTo({ top: messageList.value.scrollHeight });
    });
}

onMounted(scrollToBottom);

watch(
    () => props.chat.runs.map((run) => `${run.id}:${run.status}`).join(","),
    scrollToBottom,
);
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <CardHeader class="border-b py-4">
            <CardTitle class="flex items-center gap-2 text-base">
                <MessageSquare class="size-4" />
                Chat
                <Link
                    v-if="chat.project"
                    :href="projectRoutes.prompts(chat.project).url"
                    class="ml-auto text-xs font-normal text-muted-foreground hover:underline"
                >
                    Összes futtatás
                </Link>
            </CardTitle>
        </CardHeader>

        <CardContent class="flex flex-col p-0">
            <div
                ref="messageList"
                class="flex h-[28rem] flex-col gap-4 overflow-y-auto p-4"
            >
                <div
                    v-if="chat.runs.length === 0"
                    class="m-auto flex flex-col items-center gap-2 text-center text-sm text-muted-foreground"
                >
                    <MessageSquare class="size-8" />
                    <p>Még nincs üzenet ebben a beszélgetésben.</p>
                    <p class="text-xs">
                        Válassz projektet és agentet, majd írj lent.
                    </p>
                </div>

                <PromptRunMessage
                    v-for="run in chat.runs"
                    :key="run.id"
                    :run="run"
                />
            </div>

            <div class="border-t bg-background p-3">
                <p
                    v-if="chat.projects.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Még nincs projekt, amin a claude futhatna.
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
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Select v-model="selectedProject">
                            <SelectTrigger
                                class="w-full sm:w-56"
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
                        <Select v-model="selectedAgent">
                            <SelectTrigger
                                class="w-full sm:w-56"
                                aria-label="Agent"
                            >
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
                                    v-for="agent in chat.agents"
                                    :key="agent.id"
                                    :value="String(agent.id)"
                                >
                                    {{ agent.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex gap-2">
                        <textarea
                            v-model="form.prompt"
                            rows="2"
                            aria-label="Üzenet"
                            :placeholder="
                                selectedAgentName
                                    ? 'Üzenet az agentnek… (üresen az agent leírása indítja; Enter: küldés)'
                                    : 'Üzenet a claude-nak… (Enter: küldés, Shift+Enter: új sor)'
                            "
                            class="min-h-9 w-full flex-1 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                            @keydown="onPromptKeydown"
                        />
                        <Button
                            type="submit"
                            class="self-end"
                            :disabled="form.processing || !selectedProject"
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
                        v-if="chat.context"
                        class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                    >
                        <span>
                            {{
                                chat.context.session_id
                                    ? "Az üzenetek a korábbi beszélgetést folytatják."
                                    : "Új beszélgetés — az első üzenet indítja."
                            }}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="
                                startingContext || !chat.context.session_id
                            "
                            @click="startNewContext"
                        >
                            <MessageSquarePlus class="size-4" />
                            Új beszélgetés
                        </Button>
                    </div>
                    <InputError :message="form.errors.prompt" />
                    <InputError :message="form.errors.agent_id" />
                </form>
            </div>
        </CardContent>
    </Card>
</template>
