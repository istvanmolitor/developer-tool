<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import agents from "@/routes/agents";
import AgentForm from "./AgentForm.vue";

type Agent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model: string | null;
    tools: string[] | null;
    system_prompt: string;
};

const props = defineProps<{
    agent: Agent;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Agentek", href: agents.index().url },
            { title: "Szerkesztés", href: "" },
        ],
    },
});

const form = useForm({
    name: props.agent.name,
    slug: props.agent.slug,
    description: props.agent.description ?? "",
    model: props.agent.model ?? "",
    tools: (props.agent.tools ?? []).join(", "),
    system_prompt: props.agent.system_prompt,
});

function submit(): void {
    form.put(agents.update(props.agent.id).url);
}
</script>

<template>
    <Head :title="`${agent.name} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            :title="`${agent.name} szerkesztése`"
            description="Az agent definíciójának módosítása."
        />

        <AgentForm
            :form="form"
            submit-label="Változtatások mentése"
            :cancel-href="agents.profile(agent.id).url"
            @submit="submit"
        />
    </div>
</template>
