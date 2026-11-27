<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { FolderGit2, History } from "@lucide/vue";
import AgentAvatar from "@/components/AgentAvatar.vue";
import type { RecentContext } from "@/components/DashboardChat.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { dashboard } from "@/routes";

defineProps<{
    contexts: RecentContext[];
    activeContextId: number | null;
}>();

/** Open a conversation in the chat, keeping it in the URL. */
function openContext(context: RecentContext): void {
    router.get(
        dashboard().url,
        { context: context.id },
        {
            only: ["chat"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "";
}
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0 xl:min-h-0">
        <CardHeader class="gap-0 border-b py-3 [.border-b]:pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <History class="size-4" />
                Beszélgetések
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col p-0 xl:min-h-0 xl:flex-1">
            <p
                v-if="contexts.length === 0"
                class="p-4 text-sm text-muted-foreground"
            >
                Még nincs beszélgetésed.
            </p>

            <ul
                v-else
                class="flex max-h-[40rem] flex-col overflow-y-auto xl:max-h-none xl:min-h-0 xl:flex-1"
            >
                <li
                    v-for="context in contexts"
                    :key="context.id"
                    class="border-b last:border-b-0"
                >
                    <button
                        type="button"
                        class="flex w-full flex-col gap-1 px-4 py-3 text-left transition-colors hover:bg-muted/50"
                        :class="{ 'bg-muted': context.id === activeContextId }"
                        :aria-current="
                            context.id === activeContextId ? 'true' : undefined
                        "
                        @click="openContext(context)"
                    >
                        <span class="line-clamp-2 text-sm break-words">
                            {{ context.title || "Az agent leírása alapján" }}
                        </span>
                        <span
                            class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="context.agent"
                                class="inline-flex items-center gap-1"
                            >
                                <AgentAvatar
                                    :name="context.agent"
                                    :src="context.agent_avatar_url"
                                    class="size-4 text-[0.5rem]"
                                />
                                {{ context.agent }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <FolderGit2 class="size-3" />
                                {{ context.project }}
                            </span>
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ formatDate(context.last_activity_at) }}
                        </span>
                    </button>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
