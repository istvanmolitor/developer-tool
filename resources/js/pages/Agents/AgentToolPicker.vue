<script setup lang="ts">
import type { AgentToolGroup } from '@/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        groups: AgentToolGroup[];
        /** Every tool the agent has — this picker only ever touches its own groups' tools. */
        modelValue: string[];
        /** Whether to print the raw tool name under its Hungarian name. */
        showToolNames?: boolean;
    }>(),
    { showToolNames: true },
);

const emit = defineEmits<{ 'update:modelValue': [tools: string[]] }>();

function isSelected(name: string): boolean {
    return props.modelValue.includes(name);
}

function toggle(name: string, selected: boolean): void {
    emit(
        'update:modelValue',
        selected
            ? [...props.modelValue, name]
            : props.modelValue.filter((tool) => tool !== name),
    );
}

function groupState(group: AgentToolGroup): 'all' | 'none' | 'some' {
    const selected = group.tools.filter((tool) => isSelected(tool.name)).length;

    if (selected === 0) {
        return 'none';
    }

    return selected === group.tools.length ? 'all' : 'some';
}

function toggleGroup(group: AgentToolGroup): void {
    const names = group.tools.map((tool) => tool.name);

    emit(
        'update:modelValue',
        groupState(group) === 'all'
            ? props.modelValue.filter((tool) => !names.includes(tool))
            : [
                  ...props.modelValue,
                  ...names.filter((name) => !isSelected(name)),
              ],
    );
}
</script>

<template>
    <div class="columns-1 gap-4 sm:columns-2 xl:columns-3">
        <div
            v-for="group in groups"
            :key="group.key"
            class="mb-4 break-inside-avoid rounded-md border"
        >
            <div
                class="flex items-start justify-between gap-2 border-b px-3 py-2"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">{{ group.label }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ group.description }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="shrink-0 text-xs"
                    @click="toggleGroup(group)"
                >
                    {{ groupState(group) === 'all' ? 'Egyik sem' : 'Mind' }}
                </Button>
            </div>

            <div class="flex flex-col gap-2 px-3 py-2">
                <div
                    v-for="tool in group.tools"
                    :key="tool.name"
                    class="flex items-start gap-2"
                >
                    <Checkbox
                        :id="`tool-${tool.name}`"
                        class="mt-0.5"
                        :model-value="isSelected(tool.name)"
                        @update:model-value="
                            (value) => toggle(tool.name, value === true)
                        "
                    />
                    <Label
                        :for="`tool-${tool.name}`"
                        class="flex min-w-0 flex-col items-start gap-0.5 font-normal"
                        :title="tool.description"
                    >
                        <span class="text-sm leading-tight">{{
                            tool.label
                        }}</span>
                        <span
                            v-if="showToolNames"
                            class="font-mono text-[11px] break-all text-muted-foreground"
                            >{{ tool.name }}</span
                        >
                    </Label>
                </div>
            </div>
        </div>
    </div>
</template>
