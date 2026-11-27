<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import projects from "@/routes/projects";
import DocumentForm from "./DocumentForm.vue";

type Project = {
    id: number;
    name: string;
    path: string;
};

const props = defineProps<{
    project: Project;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const form = useForm({
    path: "",
    title: "",
    content: "",
});

function submit(): void {
    form.post(projects.documents.store(props.project.name).url);
}
</script>

<template>
    <Head :title="`Új dokumentum — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Új dokumentum"
            :description="`Új Markdown dokumentum a(z) „${project.name}” projektben.`"
        />

        <DocumentForm
            :form="form"
            submit-label="Dokumentum létrehozása"
            :cancel-href="projects.documents.index(project.name).url"
            @submit="submit"
        />
    </div>
</template>
