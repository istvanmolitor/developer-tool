<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { Bot, CheckCircle2, Clock, Loader2, XCircle } from "@lucide/vue";
import { computed } from "vue";
import AgentAvatar from "@/components/AgentAvatar.vue";
import { Badge } from "@/components/ui/badge";

export type PromptRunStatus = "pending" | "running" | "completed" | "failed";

export type ChatPromptRun = {
    id: number;
    agent_context_id: number | null;
    prompt: string | null;
    status: PromptRunStatus;
    exit_code: number | null;
    output: string | null;
    error_output: string | null;
    created_at: string;
    /** The agent that ran this prompt, or null for plain claude. */
    agent?: { name: string; avatar_url: string | null } | null;
};

defineProps<{
    run: ChatPromptRun;
}>();

/** The signed-in user, whose head sits on their own messages. */
const user = computed(() => usePage().props.auth.user);

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
    <div class="flex flex-col gap-4">
        <div class="flex flex-col items-end gap-1">
            <div class="flex max-w-[85%] items-start justify-end gap-2">
                <div
                    class="rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm wrap-anywhere whitespace-pre-wrap text-primary-foreground"
                >
                    <template v-if="run.prompt">{{ run.prompt }}</template>
                    <span v-else class="italic opacity-80"
                        >(az agent leírása alapján)</span
                    >
                </div>
                <AgentAvatar
                    :name="user.name"
                    :src="user.avatar_url"
                    class="size-7 shrink-0 text-xs"
                />
            </div>
            <p
                class="flex flex-wrap items-center justify-end gap-x-2 text-xs text-muted-foreground"
            >
                <slot name="meta" />
                <span>{{ formatDate(run.created_at) }}</span>
            </p>
        </div>

        <div class="flex items-start gap-2">
            <AgentAvatar
                v-if="run.agent"
                :name="run.agent.name"
                :src="run.agent.avatar_url"
                class="size-7 shrink-0 text-xs"
            />
            <div
                v-else
                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted"
            >
                <Bot class="size-4 text-muted-foreground" />
            </div>
            <div class="flex max-w-[85%] min-w-0 flex-col gap-1">
                <div
                    class="flex min-w-0 flex-col gap-2 rounded-2xl rounded-tl-sm bg-muted px-4 py-2 text-sm"
                >
                    <p
                        v-if="
                            run.status === 'pending' || run.status === 'running'
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
                        class="font-sans wrap-anywhere whitespace-pre-wrap"
                        >{{ run.output }}</pre>
                    <pre
                        v-if="run.error_output"
                        class="max-h-60 overflow-auto rounded-md bg-destructive/10 p-2 text-xs wrap-anywhere whitespace-pre-wrap text-destructive"
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
                                run.status === 'running' ? 'animate-spin' : ''
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
    </div>
</template>
