<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { Check, CheckCheck, FolderGit2, MessagesSquare } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import agents from "@/routes/agents";
import AgentNav from "./AgentNav.vue";

type Agent = {
    id: number;
    name: string;
    slug: string;
};

type Conversation = {
    partner: Agent | null;
    project: { id: number; name: string } | null;
    message_count: number;
    unread_count: number;
    last_message: {
        body: string;
        sender_agent_id: number | null;
        created_at: string;
    } | null;
};

type Message = {
    id: number;
    body: string;
    outgoing: boolean;
    is_read: boolean;
    read_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    agent: Agent;
    conversations: Conversation[];
    selected: { partner_id: number | null; project_id: number } | null;
    messages: Message[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Agentek", href: agents.index().url }],
    },
});

function isSelected(conversation: Conversation): boolean {
    return (
        props.selected !== null &&
        conversation.project?.id === props.selected.project_id &&
        (conversation.partner?.id ?? null) === props.selected.partner_id
    );
}

function conversationUrl(conversation: Conversation): string {
    return agents.messages(props.agent.id, {
        query: {
            project: conversation.project?.id,
            partner: conversation.partner?.id,
        },
    }).url;
}

function partnerName(conversation: Conversation | undefined): string {
    return conversation?.partner?.name ?? "Törölt agent";
}

const selectedConversation = () => props.conversations.find(isSelected);

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "";
}
</script>

<template>
    <Head :title="`${agent.name} üzenetei — Agentek`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <Heading :title="agent.name" variant="small" />
            <p class="font-mono text-xs text-muted-foreground">
                {{ agent.slug }}
            </p>
        </div>

        <AgentNav :agent-id="agent.id" />

        <div
            v-if="conversations.length === 0"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <MessagesSquare class="size-8" />
            Az agent még nem váltott üzenetet más agentekkel.
        </div>

        <div v-else class="grid min-h-0 flex-1 gap-4 md:grid-cols-[18rem_1fr]">
            <nav class="flex flex-col gap-1" aria-label="Beszélgetések">
                <Link
                    v-for="conversation in conversations"
                    :key="`${conversation.project?.id}-${conversation.partner?.id ?? 'deleted'}`"
                    :href="conversationUrl(conversation)"
                    preserve-scroll
                    class="flex flex-col gap-1 rounded-lg border p-3 text-sm transition-colors"
                    :class="
                        isSelected(conversation)
                            ? 'border-primary bg-accent'
                            : 'hover:border-primary/50'
                    "
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate font-medium">
                            {{ partnerName(conversation) }}
                        </span>
                        <Badge
                            v-if="conversation.unread_count > 0"
                            title="Az agent által még olvasatlan beérkezett üzenetek"
                        >
                            {{ conversation.unread_count }} olvasatlan
                        </Badge>
                    </div>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <FolderGit2 class="size-3" />
                        {{ conversation.project?.name }}
                        <span>· {{ conversation.message_count }} üzenet</span>
                    </div>
                    <p
                        v-if="conversation.last_message"
                        class="line-clamp-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="
                                conversation.last_message.sender_agent_id ===
                                agent.id
                            "
                            >Te:
                        </span>
                        {{ conversation.last_message.body }}
                    </p>
                </Link>
            </nav>

            <section
                class="flex min-w-0 flex-col gap-3 rounded-xl border p-4"
                :aria-label="`Beszélgetés: ${partnerName(selectedConversation())}`"
            >
                <header
                    class="flex flex-wrap items-center gap-2 border-b pb-3 text-sm"
                >
                    <span class="font-medium">{{ agent.name }}</span>
                    <span class="text-muted-foreground">↔</span>
                    <span class="font-medium">
                        {{ partnerName(selectedConversation()) }}
                    </span>
                    <Badge variant="secondary" class="gap-1">
                        <FolderGit2 class="size-3" />
                        {{ selectedConversation()?.project?.name }}
                    </Badge>
                </header>

                <div class="flex flex-col gap-3">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="flex flex-col gap-1"
                        :class="message.outgoing ? 'items-end' : 'items-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-xl px-3 py-2 text-sm whitespace-pre-wrap"
                            :class="
                                message.outgoing
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted'
                            "
                        >
                            {{ message.body }}
                        </div>
                        <div
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            {{ formatDateTime(message.created_at) }}
                            <span
                                v-if="message.is_read"
                                class="flex items-center gap-0.5"
                                :title="`Olvasva: ${formatDateTime(message.read_at)}`"
                            >
                                · <CheckCheck class="size-3" /> olvasva
                            </span>
                            <span v-else class="flex items-center gap-0.5">
                                · <Check class="size-3" /> olvasatlan
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
