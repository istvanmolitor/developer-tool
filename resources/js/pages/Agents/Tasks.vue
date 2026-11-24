<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, FolderGit2, ListTodo } from '@lucide/vue';
import FilterSelect from '@/components/FilterSelect.vue';
import Heading from '@/components/Heading.vue';
import ListSearchInput from '@/components/ListSearchInput.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import type { Paginator } from '@/components/PaginationBar.vue';
import SearchUnavailableAlert from '@/components/SearchUnavailableAlert.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { useListFilters } from '@/composables/useListFilters';
import agents from '@/routes/agents';
import projects from '@/routes/projects';
import AgentNav from './AgentNav.vue';

type Agent = {
    id: number;
    name: string;
    slug: string;
};

type Option = {
    value: string;
    label: string;
};

type Task = {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date: string | null;
    created_at: string;
    project: { id: number; name: string };
};

const props = defineProps<{
    agent: Agent;
    tasks: Paginator & { data: Task[] };
    filters: {
        q: string;
        status: string | null;
        priority: string | null;
        project: number | null;
    };
    search_degraded: boolean;
    projects: { id: number; name: string }[];
    statuses: Option[];
    priorities: Option[];
}>();

// "open" is the server's default status filter, so it stays out of the URL.
const { filters, isFiltered } = useListFilters({
    ...props.filters,
    status: props.filters.status === 'open' ? null : props.filters.status,
});

const statusOptions = [
    { value: 'all', label: 'Minden státusz' },
    ...props.statuses,
];

const projectOptions = props.projects.map((project) => ({
    value: project.id,
    label: project.name,
}));

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Agentek', href: agents.index().url }],
    },
});

function statusLabel(value: string): string {
    return props.statuses.find((o) => o.value === value)?.label ?? value;
}

function priorityLabel(value: string): string {
    return props.priorities.find((o) => o.value === value)?.label ?? value;
}

function priorityVariant(
    priority: string,
): 'destructive' | 'default' | 'secondary' {
    if (priority === 'high') {
        return 'destructive';
    }

    if (priority === 'medium') {
        return 'default';
    }

    return 'secondary';
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('hu-HU');
}
</script>

<template>
    <Head :title="`${agent.name} feladatai — Agentek`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <Heading :title="agent.name" variant="small" />
            <p class="font-mono text-xs text-muted-foreground">
                {{ agent.slug }}
            </p>
        </div>

        <AgentNav :agent-id="agent.id" />

        <div class="flex flex-wrap items-center gap-2">
            <ListSearchInput
                v-model="filters.q"
                placeholder="Keresés a feladatokban…"
            />
            <FilterSelect
                v-model="filters.status"
                :options="statusOptions"
                all-label="Nyitott"
                label="Státusz"
            />
            <FilterSelect
                v-model="filters.priority"
                :options="priorities"
                all-label="Minden prioritás"
                label="Prioritás"
            />
            <FilterSelect
                v-model="filters.project"
                :options="projectOptions"
                all-label="Minden projekt"
                label="Projekt"
            />
        </div>

        <p class="text-xs text-muted-foreground">
            A magasabb prioritás elöl, azon belül a legrégebbi.
        </p>

        <SearchUnavailableAlert v-if="search_degraded" />

        <div
            v-if="tasks.data.length === 0"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <ListTodo class="size-8" />
            {{
                isFiltered()
                    ? 'Nincs a szűrésnek megfelelő feladat.'
                    : 'Nincs az agenthez rendelt nyitott feladat.'
            }}
        </div>

        <div v-else class="flex flex-col gap-2">
            <Card
                v-for="task in tasks.data"
                :key="task.id"
                class="transition-colors hover:border-primary/50"
            >
                <CardHeader>
                    <CardTitle class="min-w-0 text-base">
                        <Link
                            :href="
                                projects.tasks.show({
                                    project: task.project.name,
                                    task: task.id,
                                }).url
                            "
                            class="flex items-center gap-2 outline-none hover:underline focus-visible:underline"
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
                        <Badge :variant="priorityVariant(task.priority)">
                            {{ priorityLabel(task.priority) }}
                        </Badge>
                        <Badge
                            :variant="
                                task.status === 'in_progress'
                                    ? 'default'
                                    : 'outline'
                            "
                        >
                            {{ statusLabel(task.status) }}
                        </Badge>
                        <Badge variant="secondary" class="gap-1">
                            <FolderGit2 class="size-3" />
                            {{ task.project.name }}
                        </Badge>
                        <Badge
                            v-if="task.due_date"
                            variant="outline"
                            class="gap-1"
                        >
                            <CalendarClock class="size-3" />
                            {{ formatDate(task.due_date) }}
                        </Badge>
                        <span class="text-muted-foreground">
                            létrehozva {{ formatDate(task.created_at) }}
                        </span>
                    </div>
                </CardHeader>
            </Card>
        </div>

        <PaginationBar :paginator="tasks" noun="feladat" />
    </div>
</template>
