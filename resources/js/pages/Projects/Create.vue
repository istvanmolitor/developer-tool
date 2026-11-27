<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import projects from '@/routes/projects';
import CreateProjectForm, { type ProjectMode } from './CreateProjectForm.vue';

const props = defineProps<{
    projects_path: string;
    directories: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projektek', href: projects.index().url },
            { title: 'Új', href: projects.create().url },
        ],
    },
});

const form = useForm({
    mode: 'existing' as ProjectMode,
    directory: '',
    name: '',
    git_url: '',
    description: '',
});

function submit(): void {
    form.post(projects.store().url);
}
</script>

<template>
    <Head title="Új projekt" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Új projekt"
            description="Válassz egy meglévő mappát, adj meg egy új mappanevet, vagy klónozz egy git repót."
        />

        <CreateProjectForm
            :form="form"
            :projects-path="props.projects_path"
            :directories="props.directories"
            :cancel-href="projects.index().url"
            @submit="submit"
        />
    </div>
</template>
