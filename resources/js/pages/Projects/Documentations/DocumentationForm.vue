<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DocumentationForm = {
    path: string;
    title: string;
    content: string;
};

defineProps<{
    form: InertiaForm<DocumentationForm>;
    submitLabel: string;
    cancelHref: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form
        class="flex max-w-3xl flex-col gap-5"
        @submit.prevent="emit('submit')"
    >
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
                <Label for="path">Útvonal</Label>
                <Input
                    id="path"
                    v-model="form.path"
                    type="text"
                    placeholder="README.md"
                    autocomplete="off"
                    class="font-mono"
                />
                <p class="text-xs text-muted-foreground">
                    Relatív .md útvonal a <code>docs/documentations</code>
                    mappán belül.
                </p>
                <InputError :message="form.errors.path" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="title"
                    >Cím
                    <span class="text-muted-foreground"
                        >(opcionális)</span
                    ></Label
                >
                <Input
                    id="title"
                    v-model="form.title"
                    type="text"
                    placeholder="Áttekintés"
                    autocomplete="off"
                />
                <InputError :message="form.errors.title" />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <Label for="content">Tartalom (Markdown)</Label>
            <textarea
                id="content"
                v-model="form.content"
                rows="20"
                placeholder="# Cím&#10;&#10;A dokumentáció Markdown tartalma…"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
            <InputError :message="form.errors.content" />
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
