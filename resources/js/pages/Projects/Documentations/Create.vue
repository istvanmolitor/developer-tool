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

const props = defineProps<{
    project: Project;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projects.index().url }],
    },
});

const form = useForm({
    path: '',
    title: '',
    content: '',
});

function submit(): void {
    form.post(projects.documentations.store(props.project.name).url);
}
</script>

<template>
    <Head :title="`Új dokumentáció — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Új dokumentáció"
            :description="`Kézi dokumentáció-bejegyzés a(z) „${project.name}” projekthez.`"
        />

        <DocumentationForm
            :form="form"
            submit-label="Dokumentáció létrehozása"
            :cancel-href="projects.documentations.index(project.name).url"
            @submit="submit"
        />
    </div>
</template>
