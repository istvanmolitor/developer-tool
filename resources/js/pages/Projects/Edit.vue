<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import projects from '@/routes/projects';
import ProjectForm from './ProjectForm.vue';

type Project = {
    id: number;
    name: string;
    path: string;
    description: string | null;
};

const props = defineProps<{
    project: Project;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projects.index().url },
            { title: 'Edit', href: '' },
        ],
    },
});

const form = useForm({
    name: props.project.name,
    path: props.project.path,
    description: props.project.description ?? '',
});

function submit(): void {
    form.put(projects.update(props.project.name).url);
}
</script>

<template>
    <Head :title="`Edit ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            :title="`Edit ${project.name}`"
            description="Update this project's details."
        />

        <ProjectForm
            :form="form"
            submit-label="Save changes"
            :cancel-href="projects.show(project.name).url"
            @submit="submit"
        />
    </div>
</template>
