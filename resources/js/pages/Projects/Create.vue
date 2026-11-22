<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import projects from '@/routes/projects';
import ProjectForm from './ProjectForm.vue';

const props = defineProps<{
    projects_path: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projects.index().url },
            { title: 'New', href: projects.create().url },
        ],
    },
});

const form = useForm({
    name: '',
    path: '',
    description: '',
});

function submit(): void {
    form.post(projects.store().url);
}
</script>

<template>
    <Head title="New project" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="New project"
            description="Register a project directory to run claude prompts against."
        />

        <ProjectForm
            :form="form"
            submit-label="Create project"
            :cancel-href="projects.index().url"
            :path-hint="`Absolute path as seen by the queue worker, e.g. ${projects_path}/my-project`"
            @submit="submit"
        />
    </div>
</template>
