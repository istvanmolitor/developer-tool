<script setup lang="ts">
import type { AgentToolGroup } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import agents from '@/routes/agents';
import AgentForm from './AgentForm.vue';

defineProps<{
    tool_groups: AgentToolGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Agentek', href: agents.index().url },
            { title: 'Új', href: agents.create().url },
        ],
    },
});

const form = useForm({
    name: '',
    slug: '',
    description: '',
    model: '',
    tools: [] as string[],
    system_prompt: '',
});

function submit(): void {
    form.post(agents.store().url);
}
</script>

<template>
    <Head title="Új agent" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Új agent"
            description="Definiálj egy szerepkört: név, leírás, modell, eszközök és a system prompt."
        />

        <AgentForm
            :form="form"
            :tool-groups="tool_groups"
            submit-label="Agent létrehozása"
            :cancel-href="agents.index().url"
            @submit="submit"
        />
    </div>
</template>
