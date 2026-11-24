<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import projects from '@/routes/projects';
import DocumentationForm from './DocumentationForm.vue';

type Project = {
    id: number;
    name: string;
    path: string;
};

type Documentation = {
    id: number;
    path: string;
    title: string | null;
    content: string | null;
};

const props = defineProps<{
    project: Project;
    documentation: Documentation;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projects.index().url }],
    },
});

const form = useForm({
    path: props.documentation.path,
    title: props.documentation.title ?? '',
    content: props.documentation.content ?? '',
});

function submit(): void {
    form.put(
        projects.documentations.update({
            project: props.project.name,
            documentation: props.documentation.id,
        }).url,
    );
}
</script>

<template>
    <Head :title="`${documentation.path} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            :title="`${documentation.path} szerkesztése`"
            :description="`Dokumentáció a(z) „${project.name}” projektben.`"
        />

        <DocumentationForm
            :form="form"
            submit-label="Változtatások mentése"
            :cancel-href="
                projects.documentations.show({
                    project: project.name,
                    documentation: documentation.id,
                }).url
            "
            @submit="submit"
        />
    </div>
</template>
