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
            { title: 'Projektek', href: projects.index().url },
            { title: 'Új', href: projects.create().url },
        ],
    },
});

const form = useForm({
    name: '',
    path: '',
    git_url: '',
    description: '',
});

function submit(): void {
    form.post(projects.store().url);
}
</script>

<template>
    <Head title="Új projekt" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Új projekt"
            description="Regisztrálj egy projektkönyvtárat, amelyen claude promptokat futtathatsz."
        />

        <ProjectForm
            :form="form"
            submit-label="Projekt létrehozása"
            :cancel-href="projects.index().url"
            :path-hint="`Abszolút útvonal a queue worker szemszögéből, pl. ${projects_path}/my-project`"
            @submit="submit"
        />
    </div>
</template>
