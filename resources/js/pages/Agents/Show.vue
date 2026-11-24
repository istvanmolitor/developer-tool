<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import agents from '@/routes/agents';

type Agent = {
    id: number;
    name: string;
    description: string | null;
    model: string | null;
    tools: string[] | null;
    system_prompt: string;
    source_path: string | null;
};

const props = defineProps<{
    agent: Agent;
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
    <Head :title="`${agent.name} — Agentek`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="agent.name"
                :description="agent.description ?? undefined"
            />
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

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Részletek</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge v-if="agent.model" variant="secondary">{{
                        agent.model
                    }}</Badge>
                    <Badge
                        v-for="tool in agent.tools ?? []"
                        :key="tool"
                        variant="outline"
                        >{{ tool }}</Badge
                    >
                    <span
                        v-if="!agent.model && !(agent.tools ?? []).length"
                        class="text-xs text-muted-foreground"
                        >Nincs modell/eszköz megadva — öröklött
                        alapértelmezés.</span
                    >
                </div>

                <div v-if="agent.source_path">
                    <p class="text-xs font-medium text-muted-foreground">
                        Forrás
                    </p>
                    <p class="mt-1 font-mono break-all">
                        {{ agent.source_path }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        System prompt
                    </p>
                    <pre
                        class="mt-1 max-h-[32rem] overflow-auto rounded-md border bg-muted/40 p-3 font-mono text-xs whitespace-pre-wrap"
                        >{{ agent.system_prompt }}</pre>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
