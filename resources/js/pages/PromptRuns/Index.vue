<script setup lang="ts">
import { Head, router, usePoll } from "@inertiajs/vue3";
import { Info, PauseCircle, Trash2 } from "@lucide/vue";
import { ref } from "vue";
import Heading from "@/components/Heading.vue";
import PaginationBar from "@/components/PaginationBar.vue";
import type { Paginator } from "@/components/PaginationBar.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { clear, index, pause } from "@/routes/prompt-runs";

type PromptRun = {
    id: number;
    prompt: string;
    status: string;
    created_at: string;
    project: { id: number; name: string } | null;
    agent: { id: number; name: string } | null;
};

const props = defineProps<{
    runs: Paginator & { data: PromptRun[] };
    paused: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Prompt sor", href: index().url }],
    },
});

// Keep the list fresh while the user watches the queue drain.
usePoll(5000);

const selected = ref<PromptRun | null>(null);

function togglePause(value: boolean | "indeterminate"): void {
    router.patch(
        pause().url,
        { paused: value === true },
        { preserveScroll: true, preserveState: true },
    );
}

function clearPending(): void {
    router.delete(clear().url, { preserveScroll: true });
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}

function preview(prompt: string): string {
    const squished = prompt.replace(/\s+/g, " ").trim();
    return squished.length > 120 ? `${squished.slice(0, 120)}…` : squished;
}
</script>

<template>
    <Head title="Prompt sor" />

    <div class="px-4 py-6">
        <Heading
            title="Prompt sor"
            description="A sorban álló, még le nem futott promptok."
        />

        <div
            class="mb-6 flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-2">
                <Checkbox
                    id="pause_prompt_runs"
                    :model-value="props.paused"
                    @update:model-value="togglePause"
                />
                <div class="flex flex-col gap-0.5">
                    <Label
                        for="pause_prompt_runs"
                        class="flex items-center gap-1.5"
                    >
                        <PauseCircle class="size-4" />
                        Következő futtatások megállítása
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        Bekapcsolva a sorban álló promptok nem futnak le, de
                        megmaradnak. Kikapcsoláskor a feldolgozás folytatódik.
                    </p>
                </div>
            </div>

            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        :disabled="props.runs.data.length === 0"
                    >
                        <Trash2 class="size-4" />
                        Sor kiürítése
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Biztosan kiüríted a sort?</DialogTitle>
                        <DialogDescription>
                            Minden sorban álló, még le nem futott prompt
                            véglegesen törlődik. Ez nem vonható vissza.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Mégse</Button>
                        </DialogClose>
                        <DialogClose as-child>
                            <Button variant="destructive" @click="clearPending">
                                Sor kiürítése
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <Badge v-if="props.paused" variant="secondary" class="mb-4">
            A futtatás szüneteltetve
        </Badge>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">#</th>
                        <th class="px-3 py-2 font-medium">Agent</th>
                        <th class="px-3 py-2 font-medium">Projekt</th>
                        <th class="px-3 py-2 font-medium">Prompt</th>
                        <th class="px-3 py-2 font-medium">Létrehozva</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="run in props.runs.data"
                        :key="run.id"
                        class="border-t"
                    >
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ run.id }}
                        </td>
                        <td class="px-3 py-2">{{ run.agent?.name ?? "—" }}</td>
                        <td class="px-3 py-2">
                            {{ run.project?.name ?? "—" }}
                        </td>
                        <td class="max-w-md truncate px-3 py-2">
                            {{ preview(run.prompt) }}
                        </td>
                        <td
                            class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatDate(run.created_at) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Részletek"
                                @click="selected = run"
                            >
                                <Info class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="props.runs.data.length === 0">
                        <td
                            colspan="6"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            Nincs sorban álló prompt.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <PaginationBar :paginator="props.runs" noun="prompt" />
        </div>
    </div>

    <Dialog
        :open="selected !== null"
        @update:open="(open) => !open && (selected = null)"
    >
        <DialogContent class="max-h-[85vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Prompt futtatás #{{ selected?.id }}</DialogTitle>
                <DialogDescription>
                    {{ selected?.agent?.name ?? "nincs agent" }} ·
                    {{ selected?.project?.name ?? "—" }} ·
                    {{ formatDate(selected?.created_at ?? null) }}
                </DialogDescription>
            </DialogHeader>
            <pre
                class="rounded-md bg-muted p-3 text-xs break-words whitespace-pre-wrap"
                >{{ selected?.prompt }}</pre>
        </DialogContent>
    </Dialog>
</template>
