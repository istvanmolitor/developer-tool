<script setup lang="ts">
import { Bot } from "@lucide/vue";
import { computed } from "vue";
import AgentAvatar from "@/components/AgentAvatar.vue";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

export type AgentOption = {
    id: number;
    name: string;
    avatar_url?: string | null;
};

const props = withDefaults(
    defineProps<{
        /** The selected value: an agent id as string, or `noneValue`. */
        modelValue: string;
        agents: AgentOption[];
        /** Sentinel value standing for "no agent". */
        noneValue?: string;
        /** Label for the no-agent option; omit to hide that option entirely. */
        noneLabel?: string;
        id?: string;
        ariaLabel?: string;
        triggerClass?: string;
    }>(),
    {
        noneValue: "none",
        noneLabel: undefined,
        id: undefined,
        ariaLabel: "Agent",
        triggerClass: "w-full",
    },
);

const emit = defineEmits<{
    (event: "update:modelValue", value: string): void;
}>();

/** The agent matching the current value, or null for the no-agent option. */
const selected = computed(
    () =>
        props.agents.find((agent) => String(agent.id) === props.modelValue) ??
        null,
);
</script>

<template>
    <Select
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', String($event))"
    >
        <SelectTrigger :id="id" :class="triggerClass" :aria-label="ariaLabel">
            <SelectValue>
                <span class="flex items-center gap-2">
                    <AgentAvatar
                        v-if="selected"
                        :name="selected.name"
                        :src="selected.avatar_url"
                        class="size-5 text-[0.625rem]"
                    />
                    <Bot v-else class="size-4 text-muted-foreground" />
                    {{ selected ? selected.name : noneLabel }}
                </span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-if="noneLabel !== undefined" :value="noneValue">
                <span class="flex items-center gap-2">
                    <Bot class="size-4 text-muted-foreground" />
                    {{ noneLabel }}
                </span>
            </SelectItem>
            <SelectItem
                v-for="agent in agents"
                :key="agent.id"
                :value="String(agent.id)"
            >
                <span class="flex items-center gap-2">
                    <AgentAvatar
                        :name="agent.name"
                        :src="agent.avatar_url"
                        class="size-5 text-[0.625rem]"
                    />
                    {{ agent.name }}
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
