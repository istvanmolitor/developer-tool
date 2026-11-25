<script setup lang="ts">
import type { InertiaForm } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

type Option = { value: string; label: string };
type AgentOption = { id: number; name: string };

type TaskFormFields = {
    title: string;
    type: string;
    agent_id: string;
    description: string;
    status: string;
    auto_run: boolean;
    priority: string;
    due_date: string;
};

const props = defineProps<{
    form: InertiaForm<TaskFormFields>;
    statuses: Option[];
    priorities: Option[];
    types: Option[];
    agents: AgentOption[];
    submitLabel: string;
    cancelHref: string;
}>();

const emit = defineEmits<{ submit: [] }>();

// Sentinel value for the "unassigned" select item — empty string isn't a valid
// Select item value, so use a distinct token and map it back to '' on submit.
const UNASSIGNED = "none";

function labelFor(options: Option[], value: string): string | undefined {
    return options.find((option) => option.value === value)?.label;
}

function agentLabel(value: string): string {
    if (value === "" || value === UNASSIGNED) return "Nincs hozzárendelve";

    return (
        props.agents.find((agent) => String(agent.id) === value)?.name ??
        "Nincs hozzárendelve"
    );
}
</script>

<template>
    <form
        class="flex max-w-3xl flex-col gap-5"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-2">
            <Label for="title">Cím</Label>
            <Input
                id="title"
                v-model="form.title"
                type="text"
                placeholder="Mit kell elvégezni?"
                autocomplete="off"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="type">Típus</Label>
            <Select v-model="form.type">
                <SelectTrigger id="type" class="w-full sm:max-w-xs">
                    <SelectValue>
                        {{ labelFor(props.types, form.type) }}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in types"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.type" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="agent"
                >Felelős agent
                <span class="text-muted-foreground">(opcionális)</span>
            </Label>
            <Select
                :model-value="form.agent_id === '' ? UNASSIGNED : form.agent_id"
                @update:model-value="
                    (value) =>
                        (form.agent_id =
                            String(value) === UNASSIGNED ? '' : String(value))
                "
            >
                <SelectTrigger id="agent" class="w-full sm:max-w-xs">
                    <SelectValue>{{ agentLabel(form.agent_id) }}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="UNASSIGNED">
                        Nincs hozzárendelve
                    </SelectItem>
                    <SelectItem
                        v-for="agent in agents"
                        :key="agent.id"
                        :value="String(agent.id)"
                    >
                        {{ agent.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.agent_id" />
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div class="flex flex-col gap-2">
                <Label for="status">Státusz</Label>
                <Select v-model="form.status">
                    <SelectTrigger id="status" class="w-full">
                        <SelectValue>
                            {{ labelFor(props.statuses, form.status) }}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="priority">Prioritás</Label>
                <Select v-model="form.priority">
                    <SelectTrigger id="priority" class="w-full">
                        <SelectValue>
                            {{ labelFor(props.priorities, form.priority) }}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in priorities"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.priority" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="due_date"
                    >Határidő
                    <span class="text-muted-foreground">(opcionális)</span>
                </Label>
                <Input id="due_date" v-model="form.due_date" type="date" />
                <InputError :message="form.errors.due_date" />
            </div>
        </div>

        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="auto_run"
                    :model-value="form.auto_run"
                    @update:model-value="
                        (value) => (form.auto_run = value === true)
                    "
                />
                <Label for="auto_run">Automatikus végrehajtás</Label>
            </div>
            <p class="pl-6 text-xs text-muted-foreground">
                Bejelölve a feladat automatikus futtatásra van jelölve.
            </p>
            <InputError class="pl-6" :message="form.errors.auto_run" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="description"
                >Leírás
                <span class="text-muted-foreground">(opcionális)</span>
            </Label>
            <textarea
                id="description"
                v-model="form.description"
                rows="6"
                placeholder="Részletek, elfogadási feltételek…"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">{{
                submitLabel
            }}</Button>
            <Button type="button" variant="ghost" as-child>
                <a :href="cancelHref">Mégse</a>
            </Button>
        </div>
    </form>
</template>
