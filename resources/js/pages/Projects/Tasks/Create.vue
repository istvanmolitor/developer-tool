<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import projects from "@/routes/projects";
import TaskForm from "./TaskForm.vue";

type Option = { value: string; label: string };
type AgentOption = { id: number; name: string };

type Project = {
    id: number;
    name: string;
    path: string;
};

const props = defineProps<{
    project: Project;
    statuses: Option[];
    priorities: Option[];
    types: Option[];
    agents: AgentOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const form = useForm({
    title: "",
    type: "development",
    agent_id: "",
    description: "",
    status: "todo",
    auto_run: false,
    priority: "medium",
    due_date: "",
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        agent_id: data.agent_id === "" ? null : Number(data.agent_id),
    })).post(projects.tasks.store(props.project.name).url);
}
</script>

<template>
    <Head :title="`Új feladat — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Új feladat"
            :description="`Feladat a(z) „${project.name}” projekthez.`"
        />

        <TaskForm
            :form="form"
            :statuses="statuses"
            :priorities="priorities"
            :types="types"
            :agents="agents"
            submit-label="Feladat létrehozása"
            :cancel-href="projects.tasks.index(project.name).url"
            @submit="submit"
        />
    </div>
</template>
