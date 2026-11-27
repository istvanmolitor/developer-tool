<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import {
    Bot,
    MessageSquare,
    Pencil,
    Plus,
    RefreshCw,
    Search,
} from "@lucide/vue";
import { computed, ref } from "vue";
import AgentAvatar from "@/components/AgentAvatar.vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import agentRoutes from "@/routes/agents";

type Agent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model: string | null;
    source_path: string | null;
    avatar_url: string | null;
};

const props = defineProps<{
    agents: Agent[];
}>();

const search = ref("");

const filteredAgents = computed<Agent[]>(() => {
    const term = search.value.trim().toLocaleLowerCase("hu");

    if (term === "") {
        return props.agents;
    }

    return props.agents.filter((agent) =>
        agent.name.toLocaleLowerCase("hu").includes(term),
    );
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Agentek", href: agentRoutes.index().url }],
    },
});

const syncing = ref(false);

function syncAgents(): void {
    router.post(
        agentRoutes.sync().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (syncing.value = true),
            onFinish: () => (syncing.value = false),
        },
    );
}
</script>

<template>
    <Head title="Agentek" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Agentek"
                description="A szerepkörök, amelyekkel a promptok futnak."
            />
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="syncing"
                    title="Agentek frissítése az agent fájlokból"
                    @click="syncAgents"
                >
                    <RefreshCw
                        class="size-4"
                        :class="{ 'animate-spin': syncing }"
                    />
                    Szinkron
                </Button>
                <Button as-child>
                    <Link :href="agentRoutes.create().url">
                        <Plus class="size-4" />
                        Új agent
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="agents.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <Bot class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincsenek agentek</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Hozz létre egyet az
                <Link
                    :href="agentRoutes.create().url"
                    class="font-medium underline"
                    >Új agent</Link
                >
                gombbal, vagy töltsd be a definíciókat a
                <span class="font-medium">Szinkron</span> gombbal.
            </p>
        </div>

        <template v-else>
            <div class="relative max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Keresés név alapján…"
                    aria-label="Agentek keresése"
                    class="pl-8"
                />
            </div>

            <p
                v-if="filteredAgents.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Nincs a(z) „{{ search }}” keresésnek megfelelő agent.
            </p>

            <div
                v-else
                class="grid auto-rows-min gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
            >
                <Card
                    v-for="agent in filteredAgents"
                    :key="agent.id"
                    class="group relative flex h-full flex-col items-center gap-3 p-4 text-center transition-colors hover:border-primary/50"
                >
                    <div
                        class="absolute top-1 right-1 flex items-center opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
                    >
                        <Button variant="ghost" size="icon-sm" as-child>
                            <Link
                                :href="agentRoutes.show(agent.id).url"
                                :title="`Chat a(z) ${agent.name} agenttel`"
                                :aria-label="`Chat a(z) ${agent.name} agenttel`"
                            >
                                <MessageSquare class="size-3.5" />
                            </Link>
                        </Button>
                        <Button variant="ghost" size="icon-sm" as-child>
                            <Link
                                :href="agentRoutes.edit(agent.id).url"
                                :title="`${agent.name} szerkesztése`"
                                :aria-label="`${agent.name} szerkesztése`"
                            >
                                <Pencil class="size-3.5" />
                            </Link>
                        </Button>
                    </div>

                    <Link
                        :href="agentRoutes.profile(agent.id).url"
                        class="flex w-full flex-col items-center gap-2 outline-none"
                    >
                        <AgentAvatar
                            :name="agent.name"
                            :src="agent.avatar_url"
                            class="size-20"
                        />
                        <span
                            class="w-full text-sm leading-tight font-medium break-words hyphens-auto group-hover:underline"
                        >
                            {{ agent.name }}
                        </span>
                    </Link>

                    <Badge
                        v-if="agent.model"
                        variant="secondary"
                        class="mt-auto"
                    >
                        {{ agent.model }}
                    </Badge>
                </Card>
            </div>
        </template>
    </div>
</template>
