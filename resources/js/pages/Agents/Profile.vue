<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import agents from '@/routes/agents';
import AgentNav from './AgentNav.vue';

type Tool = {
    name: string;
    label: string;
    description: string | null;
    group: string | null;
    known: boolean;
};

type Agent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model: string | null;
    tools: string[] | null;
    system_prompt: string;
    source_path: string | null;
};

const props = defineProps<{
    agent: Agent;
    tools: Tool[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Agentek', href: agents.index().url }],
    },
});

function destroy(): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${props.agent.name}” agentet? Ez a művelet nem vonható vissza.`,
        )
    ) {
        return;
    }

    router.delete(agents.destroy(props.agent.id).url);
}
</script>

<template>
    <Head :title="`${agent.name} profilja — Agentek`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <Heading :title="agent.name" variant="small" />
                <p class="font-mono text-xs text-muted-foreground">
                    {{ agent.slug }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="agents.edit(agent.id).url">
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

        <AgentNav :agent-id="agent.id" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Adatok</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-5 text-sm">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Leírás
                        </p>
                        <p
                            v-if="agent.description"
                            class="mt-1 whitespace-pre-line"
                        >
                            {{ agent.description }}
                        </p>
                        <p v-else class="mt-1 text-muted-foreground italic">
                            Nincs leírás.
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Modell
                        </p>
                        <div class="mt-1">
                            <Badge v-if="agent.model" variant="secondary">{{
                                agent.model
                            }}</Badge>
                            <span v-else class="text-muted-foreground"
                                >Öröklött alapértelmezés</span
                            >
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Eszközök
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <Badge
                                v-for="tool in tools"
                                :key="tool.name"
                                variant="outline"
                                :title="
                                    tool.description
                                        ? `${tool.name} — ${tool.description}`
                                        : tool.name
                                "
                                :class="tool.known ? undefined : 'font-mono'"
                                >{{ tool.label }}</Badge
                            >
                            <span
                                v-if="!tools.length"
                                class="text-muted-foreground"
                                >Öröklött alapértelmezés — minden eszköz</span
                            >
                        </div>
                    </div>

                    <div v-if="agent.source_path">
                        <p class="text-xs font-medium text-muted-foreground">
                            Forrás
                        </p>
                        <p class="mt-1 font-mono break-all">
                            {{ agent.source_path }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="flex flex-col">
                <CardHeader>
                    <CardTitle class="text-base">System prompt</CardTitle>
                </CardHeader>
                <CardContent class="min-h-0 flex-1">
                    <pre
                        class="h-full max-h-[40rem] overflow-auto rounded-md border bg-muted/40 p-3 font-mono text-xs whitespace-pre-wrap"
                        >{{ agent.system_prompt }}</pre>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
