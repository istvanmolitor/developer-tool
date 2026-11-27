<script setup lang="ts">
import type { AgentToolGroup } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import agents from '@/routes/agents';
import AgentForm from './AgentForm.vue';

type Agent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model: string | null;
    tools: string[] | null;
    system_prompt: string;
    avatar_url: string | null;
};

const props = defineProps<{
    agent: Agent;
    tool_groups: AgentToolGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Agentek', href: agents.index().url },
            { title: 'Szerkesztés', href: '' },
        ],
    },
});

const form = useForm({
    // The picture is uploaded with the form, so the update is posted as
    // multipart with a method override rather than a plain PUT.
    _method: 'put',
    name: props.agent.name,
    slug: props.agent.slug,
    description: props.agent.description ?? '',
    model: props.agent.model ?? '',
    tools: props.agent.tools ?? [],
    system_prompt: props.agent.system_prompt,
    avatar: null as File | null,
    remove_avatar: false,
});

function submit(): void {
    form.post(agents.update(props.agent.id).url, { forceFormData: true });
}
</script>

<template>
    <Head :title="`${agent.name} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            :title="`${agent.name} szerkesztése`"
            description="Az agent adatai, eszközei és system promptja."
        />

        <AgentForm
            :form="form"
            :avatar-url="agent.avatar_url"
            :tool-groups="tool_groups"
            submit-label="Változtatások mentése"
            :cancel-href="agents.profile(agent.id).url"
            @submit="submit"
        />
    </div>
</template>
