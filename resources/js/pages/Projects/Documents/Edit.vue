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

type Document = {
    id: number;
    path: string;
    title: string | null;
    content: string | null;
};

const props = defineProps<{
    project: Project;
    document: Document;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const form = useForm({
    path: props.document.path,
    title: props.document.title ?? "",
    content: props.document.content ?? "",
});

function submit(): void {
    form.put(
        projects.documents.update({
            project: props.project.name,
            document: props.document.id,
        }).url,
    );
}
</script>

<template>
    <Head :title="`${document.path} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            :title="`${document.path} szerkesztése`"
            :description="`Dokumentum a(z) „${project.name}” projektben.`"
        />

        <DocumentForm
            :form="form"
            submit-label="Változtatások mentése"
            :cancel-href="
                projects.documents.show({
                    project: project.name,
                    document: document.id,
                }).url
            "
            @submit="submit"
        />
    </div>
</template>
