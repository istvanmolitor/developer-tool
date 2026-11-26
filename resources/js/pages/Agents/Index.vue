<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import {
    Bot,
    ChevronRight,
    MessageSquare,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
} from "@lucide/vue";
import { computed, ref } from "vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import agentRoutes from "@/routes/agents";

type Agent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model: string | null;
    source_path: string | null;
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

function destroy(agent: Agent): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${agent.name}” agentet? Ez a művelet nem vonható vissza.`,
        )
    ) {
        return;
    }

    router.delete(agentRoutes.destroy(agent.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Agentek" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Agentek"
                description="Szerepkörök, amelyeket a Claude futtat — mindegyiknek saját system promptja, modellje és eszközei vannak."
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
                class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <Card
                    v-for="agent in filteredAgents"
                    :key="agent.id"
                    class="relative flex h-full flex-col transition-colors hover:border-primary/50"
                >
                    <div class="absolute top-2 right-2 flex items-center">
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
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive hover:text-destructive"
                            :title="`${agent.name} törlése`"
                            :aria-label="`${agent.name} törlése`"
                            @click="destroy(agent)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                    <CardHeader class="pr-28">
                        <CardTitle class="min-w-0">
                            <Link
                                :href="agentRoutes.profile(agent.id).url"
                                :title="agent.name"
                                class="flex min-w-0 items-center gap-2 outline-none hover:underline focus-visible:underline"
                            >
                                <Bot
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <span class="truncate">{{ agent.name }}</span>
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        class="mt-auto flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <code class="font-mono">{{ agent.slug }}</code>
                        <Badge v-if="agent.model" variant="secondary">{{
                            agent.model
                        }}</Badge>
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>
</template>
