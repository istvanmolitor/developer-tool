<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProjectForm = {
    name: string;
    path: string;
    description: string;
};

defineProps<{
    form: InertiaForm<ProjectForm>;
    submitLabel: string;
    cancelHref: string;
    pathHint?: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form
        class="flex max-w-2xl flex-col gap-5"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="my-project"
                autocomplete="off"
            />
            <p class="text-xs text-muted-foreground">
                Shown in the sidebar and used in the project URL.
            </p>
            <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="path">Path</Label>
            <Input
                id="path"
                v-model="form.path"
                type="text"
                placeholder="/projects/my-project"
                autocomplete="off"
            />
            <p v-if="pathHint" class="text-xs text-muted-foreground">
                {{ pathHint }}
            </p>
            <InputError :message="form.errors.path" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="description"
                >Description
                <span class="text-muted-foreground">(optional)</span></Label
            >
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                placeholder="What is this project about?"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">{{
                submitLabel
            }}</Button>
            <Button type="button" variant="ghost" as-child>
                <a :href="cancelHref">Cancel</a>
            </Button>
        </div>
    </form>
</template>
