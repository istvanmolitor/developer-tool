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

type Task = {
    id: number;
    title: string;
    type: string;
    agent_id: number | null;
    description: string | null;
    status: string;
    priority: string;
    due_date: string | null;
};

const props = defineProps<{
    project: Project;
    task: Task;
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
    title: props.task.title,
    type: props.task.type,
    agent_id: props.task.agent_id === null ? "" : String(props.task.agent_id),
    description: props.task.description ?? "",
    status: props.task.status,
    priority: props.task.priority,
    due_date: props.task.due_date ?? "",
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        agent_id: data.agent_id === "" ? null : Number(data.agent_id),
    })).put(
        projects.tasks.update({
            project: props.project.name,
            task: props.task.id,
        }).url,
    );
}
</script>

<template>
    <Head :title="`Feladat szerkesztése — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Feladat szerkesztése"
            :description="`„${project.name}” projekt feladata.`"
        />

        <TaskForm
            :form="form"
            :statuses="statuses"
            :priorities="priorities"
            :types="types"
            :agents="agents"
            submit-label="Módosítások mentése"
            :cancel-href="projects.tasks.index(project.name).url"
            @submit="submit"
        />
    </div>
</template>
