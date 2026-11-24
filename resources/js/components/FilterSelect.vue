<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { value: string | number; label: string };

const ALL = '__all__';

const model = defineModel<string | number | null>({ default: null });

const props = defineProps<{
    options: Option[];
    /** Label of the "no filter" choice, e.g. "Minden státusz". */
    allLabel?: string;
    label: string;
}>();

// reka-ui's Select only takes non-empty strings, so "no filter" gets a
// sentinel value and numeric options travel as strings.
const selected = computed<string>({
    get: () => (model.value === null ? ALL : String(model.value)),
    set: (value) => {
        if (value === ALL) {
            model.value = null;

            return;
        }

        const option = props.options.find((o) => String(o.value) === value);
        model.value = option?.value ?? null;
    },
});

const selectedLabel = computed(
    () =>
        props.options.find((o) => String(o.value) === selected.value)?.label ??
        props.allLabel ??
        'Mind',
);
</script>

<template>
    <Select v-model="selected">
        <SelectTrigger size="sm" class="w-full sm:w-44" :aria-label="label">
            <SelectValue>{{ selectedLabel }}</SelectValue>
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-if="allLabel !== undefined" :value="ALL">
                {{ allLabel }}
            </SelectItem>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="String(option.value)"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
