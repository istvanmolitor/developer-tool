<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { ArrowLeft, History } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Button } from "@/components/ui/button";
import projects from "@/routes/projects";
import ProjectNav from "../ProjectNav.vue";
import TaskChangeList from "./TaskChangeList.vue";
import type { TaskChangeEntry } from "./TaskChangeList.vue";

type Project = { id: number; name: string; path: string };

defineProps<{
    project: Project;
    entries: TaskChangeEntry[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});
</script>

<template>
    <Head :title="`Változásnapló — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="project.name"
                description="A feladatok változásnaplója."
            />
            <Button variant="outline" size="sm" as-child>
                <Link :href="projects.tasks.index(project.name).url">
                    <ArrowLeft class="size-4" />
                    Feladatok
                </Link>
            </Button>
        </div>

        <ProjectNav :name="project.name" />

        <div
            v-if="entries.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <History class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincs változás</p>
            <p class="mt-1 text-sm text-muted-foreground">
                A feladatok státuszának, prioritásának, típusának, felelősének
                és határidejének változásai itt jelennek meg.
            </p>
        </div>

        <template v-else>
            <TaskChangeList
                :entries="entries"
                :project-name="project.name"
                show-task
            />

            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
            >
                <span>
                    {{ pagination.current_page }} /
                    {{ pagination.last_page }} oldal ·
                    {{ pagination.total }} bejegyzés
                </span>
                <div class="flex gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        v-if="pagination.prev_page_url"
                        as-child
                    >
                        <Link :href="pagination.prev_page_url" preserve-scroll>
                            Előző
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        v-if="pagination.next_page_url"
                        as-child
                    >
                        <Link :href="pagination.next_page_url" preserve-scroll>
                            Következő
                        </Link>
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
