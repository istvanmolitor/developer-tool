<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import projects from '@/routes/projects';
import ProjectForm from './ProjectForm.vue';

type Project = {
    id: number;
    name: string;
    path: string;
    git_url: string | null;
    description: string | null;
    is_system: boolean;
};

const props = defineProps<{
    project: Project;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projektek', href: projects.index().url },
            { title: 'Szerkesztés', href: '' },
        ],
    },
});

const form = useForm({
    name: props.project.name,
    path: props.project.path,
    git_url: props.project.git_url ?? '',
    description: props.project.description ?? '',
});

function submit(): void {
    form.put(projects.update(props.project.name).url);
}
</script>

<template>
    <Head :title="`${project.name} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            :title="`${project.name} szerkesztése`"
            description="A projekt adatainak módosítása."
        />

        <ProjectForm
            :form="form"
            submit-label="Változtatások mentése"
            :cancel-href="projects.show(project.name).url"
            :path-locked="project.is_system"
            :path-hint="
                project.is_system
                    ? 'A rendszer saját projektjének útvonala rögzített, nem módosítható.'
                    : undefined
            "
            @submit="submit"
        />
    </div>
</template>
