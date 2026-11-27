<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProjectForm = {
    name: string;
    path: string;
    git_url: string;
    description: string;
};

defineProps<{
    form: InertiaForm<ProjectForm>;
    submitLabel: string;
    cancelHref: string;
    pathHint?: string;
    pathLocked?: boolean;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form
        class="flex max-w-2xl flex-col gap-5"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-2">
            <Label for="name">Név</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="my-project"
                autocomplete="off"
            />
            <p class="text-xs text-muted-foreground">
                Megjelenik az oldalsávban, és a projekt URL-jében szerepel.
            </p>
            <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="path">Útvonal</Label>
            <Input
                id="path"
                v-model="form.path"
                type="text"
                placeholder="/projects/my-project"
                autocomplete="off"
                :disabled="pathLocked"
                :readonly="pathLocked"
            />
            <p v-if="pathHint" class="text-xs text-muted-foreground">
                {{ pathHint }}
            </p>
            <InputError :message="form.errors.path" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="git_url"
                >Git link
                <span class="text-muted-foreground">(opcionális)</span></Label
            >
            <Input
                id="git_url"
                v-model="form.git_url"
                type="text"
                placeholder="https://github.com/user/repo.git"
                autocomplete="off"
            />
            <p class="text-xs text-muted-foreground">
                A tároló URL-je (HTTPS vagy SSH), pl. git clone címe.
            </p>
            <InputError :message="form.errors.git_url" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="description"
                >Leírás
                <span class="text-muted-foreground">(opcionális)</span></Label
            >
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                placeholder="Miről szól ez a projekt?"
                class="w-full rounded-md border border-input bg-white text-neutral-900 px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-neutral-500 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
