<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import {
    Bot,
    CalendarClock,
    ChevronRight,
    FolderKanban,
    ListTodo,
    Pencil,
    Play,
    Trash2,
    Zap,
} from "@lucide/vue";
import { computed } from "vue";
import FilterSelect from "@/components/FilterSelect.vue";
import Heading from "@/components/Heading.vue";
import ListSearchInput from "@/components/ListSearchInput.vue";
import PaginationBar from "@/components/PaginationBar.vue";
import type { Paginator } from "@/components/PaginationBar.vue";
import SearchUnavailableAlert from "@/components/SearchUnavailableAlert.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useListFilters } from "@/composables/useListFilters";
import projects from "@/routes/projects";
import taskRoutes from "@/routes/tasks";

type Option = { value: string; label: string };

type Task = {
    id: number;
    title: string;
    type: string;
    description: string | null;
    status: string;
    auto_run: boolean;
    priority: string;
    due_date: string | null;
    agent: { id: number; name: string } | null;
    project: { id: number; name: string };
};

type Filters = {
    q: string;
    project: number | null;
    status: string | null;
    priority: string | null;
    type: string | null;
    agent: number | null;
    sort: string;
};

const props = defineProps<{
    tasks: Paginator & { data: Task[] };
    filters: Filters;
    search_degraded: boolean;
    statuses: Option[];
    priorities: Option[];
    types: Option[];
    agents: { id: number; name: string }[];
    projects: { id: number; name: string }[];
    sorts: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Feladatok", href: taskRoutes.index().url }],
    },
});

// A rendezés (sort) nem szűrő: mindig érvényes értéke van, ezért a
// „Szűrők törlése” gomb és az üres állapot nem veszi figyelembe.
const { filters } = useListFilters<Filters>({ ...props.filters });

const agentOptions = props.agents.map((agent) => ({
    value: agent.id,
    label: agent.name,
}));

const projectOptions = props.projects.map((project) => ({
    value: project.id,
    label: project.name,
}));

const hasFilters = computed(
    () =>
        filters.q !== "" ||
        filters.project !== null ||
        filters.status !== null ||
        filters.priority !== null ||
        filters.type !== null ||
        filters.agent !== null,
);

function clearFilters(): void {
    filters.q = "";
    filters.project = null;
    filters.status = null;
    filters.priority = null;
    filters.type = null;
    filters.agent = null;
}

function statusLabel(value: string): string {
    return props.statuses.find((o) => o.value === value)?.label ?? value;
}

function priorityLabel(value: string): string {
    return props.priorities.find((o) => o.value === value)?.label ?? value;
}

function typeLabel(value: string): string {
    return props.types.find((o) => o.value === value)?.label ?? value;
}

// A befejezett (kész vagy lezárt) feladatok terminálisak: halványítva,
// áthúzva jelennek meg, és nem indíthatók újra.
function isCompleted(status: string): boolean {
    return status === "done" || status === "closed";
}

function priorityVariant(priority: string): "blocked" | "review" | "outline" {
    if (priority === "high") return "blocked";
    if (priority === "medium") return "review";
    return "outline";
}

function changeStatus(task: Task, status: string): void {
    if (status === task.status) return;

    router.patch(
        projects.tasks.update({ project: task.project.name, task: task.id })
            .url,
        { status },
        { preserveScroll: true },
    );
}

function run(task: Task): void {
    if (!task.agent) return;

    router.post(
        projects.tasks.run({ project: task.project.name, task: task.id }).url,
        {},
        { preserveScroll: true },
    );
}

function destroy(task: Task): void {
    if (!window.confirm(`Törlöd a(z) „${task.title}” feladatot?`)) {
        return;
    }

    router.delete(
        projects.tasks.destroy({ project: task.project.name, task: task.id })
            .url,
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString("hu-HU") : "";
}
</script>

<template>
    <Head title="Feladatok" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Feladatok"
            description="Az összes projekt feladata egy helyen."
        />

        <div class="flex flex-wrap items-center gap-2">
            <ListSearchInput
                v-model="filters.q"
                placeholder="Keresés a feladatokban…"
            />
            <FilterSelect
                v-model="filters.agent"
                :options="agentOptions"
                all-label="Minden felelős"
                label="Felelős"
            />
            <FilterSelect
                v-model="filters.project"
                :options="projectOptions"
                all-label="Minden projekt"
                label="Projekt"
            />
            <FilterSelect
                v-model="filters.status"
                :options="statuses"
                all-label="Minden státusz"
                label="Státusz"
            />
            <FilterSelect
                v-model="filters.priority"
                :options="priorities"
                all-label="Minden prioritás"
                label="Prioritás"
            />
            <FilterSelect
                v-model="filters.type"
                :options="types"
                all-label="Minden típus"
                label="Típus"
            />
            <Select v-model="filters.sort">
                <SelectTrigger
                    size="sm"
                    class="w-full sm:w-48"
                    aria-label="Rendezés"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in sorts"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button
                v-if="hasFilters"
                variant="ghost"
                size="sm"
                @click="clearFilters"
            >
                Szűrők törlése
            </Button>
        </div>

        <SearchUnavailableAlert v-if="search_degraded" />

        <div
            v-if="tasks.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <ListTodo class="mx-auto mb-3 size-8 text-muted-foreground" />
            <template v-if="hasFilters">
                <p class="font-medium">Nincs a szűrésnek megfelelő feladat</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Módosítsd a keresést vagy a szűrőket.
                </p>
            </template>
            <template v-else>
                <p class="font-medium">Még nincs feladat</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Hozz létre egyet egy projekt oldalán, vagy kérd meg
                    Claude-ot az MCP-n keresztül.
                </p>
            </template>
        </div>

        <div v-else class="flex flex-col gap-2">
            <Card
                v-for="task in tasks.data"
                :key="task.id"
                class="transition-colors hover:border-primary/50"
                :class="isCompleted(task.status) ? 'opacity-70' : ''"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <CardTitle class="min-w-0 text-base">
                            <Link
                                :href="
                                    projects.tasks.show({
                                        project: task.project.name,
                                        task: task.id,
                                    }).url
                                "
                                class="flex items-center gap-2 outline-none hover:underline focus-visible:underline"
                                :class="
                                    isCompleted(task.status)
                                        ? 'line-through'
                                        : ''
                                "
                            >
                                {{ task.title }}
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </CardTitle>
                        <div
                            class="mt-1.5 flex flex-wrap items-center gap-2 text-xs"
                        >
                            <Badge v-if="task.agent" variant="ai" class="gap-1">
                                <Bot class="size-3" />
                                {{ task.agent.name }}
                            </Badge>
                            <Link
                                :href="
                                    projects.tasks.index(task.project.name).url
                                "
                                class="outline-none"
                            >
                                <Badge
                                    variant="outline"
                                    class="gap-1 hover:border-primary/50"
                                >
                                    <FolderKanban class="size-3" />
                                    {{ task.project.name }}
                                </Badge>
                            </Link>
                            <Badge
                                :variant="
                                    task.type === 'bug'
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ typeLabel(task.type) }}
                            </Badge>
                            <Badge :variant="priorityVariant(task.priority)">
                                {{ priorityLabel(task.priority) }}
                            </Badge>
                            <Badge
                                v-if="task.auto_run"
                                variant="outline"
                                class="gap-1"
                                title="Automatikus végrehajtás"
                            >
                                <Zap class="size-3" />
                                Automatikus
                            </Badge>
                            <Badge
                                v-if="task.due_date"
                                variant="outline"
                                class="gap-1"
                            >
                                <CalendarClock class="size-3" />
                                {{ formatDate(task.due_date) }}
                            </Badge>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            v-if="!isCompleted(task.status)"
                            variant="ghost"
                            size="sm"
                            class="text-primary hover:text-primary"
                            :disabled="!task.agent"
                            :title="
                                task.agent
                                    ? `Elindítás: ${task.agent.name} megkapja a feladatot`
                                    : 'Rendelj hozzá egy agentet az elindításhoz'
                            "
                            @click="run(task)"
                        >
                            <Play class="size-3.5" />
                        </Button>
                        <Select
                            :model-value="task.status"
                            @update:model-value="
                                (value) => changeStatus(task, String(value))
                            "
                        >
                            <SelectTrigger size="sm" class="w-36">
                                <SelectValue>
                                    {{ statusLabel(task.status) }}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    projects.tasks.edit({
                                        project: task.project.name,
                                        task: task.id,
                                    }).url
                                "
                            >
                                <Pencil class="size-3.5" />
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(task)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardHeader>
            </Card>
        </div>

        <PaginationBar :paginator="tasks" noun="feladat" />
    </div>
</template>
