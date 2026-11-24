<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import {
    Bot,
    ChevronRight,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
} from "@lucide/vue";
import { ref } from "vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import agentRoutes from "@/routes/agents";

type Agent = {
    id: number;
    name: string;
    slug: string;
    model: string | null;
    source_path: string | null;
};

defineProps<{
    agents: Agent[];
}>();

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

        <div
            v-else
            class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Card
                v-for="agent in agents"
                :key="agent.id"
                class="flex h-full flex-col transition-colors hover:border-primary/50"
            >
                <CardHeader>
                    <CardTitle class="min-w-0">
                        <Link
                            :href="agentRoutes.show(agent.id).url"
                            class="flex items-center gap-2 break-all outline-none hover:underline focus-visible:underline"
                        >
                            <Bot
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            {{ agent.name }}
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
                    <div
                        class="flex w-full items-center justify-end gap-1 pt-1"
                    >
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="agentRoutes.edit(agent.id).url">
                                <Pencil class="size-3.5" />
                                Szerkesztés
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(agent)"
                        >
                            <Trash2 class="size-3.5" />
                            Törlés
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
