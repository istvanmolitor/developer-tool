<script setup lang="ts">
import type { InertiaForm } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type AgentForm = {
    name: string;
    slug: string;
    description: string;
    model: string;
    tools: string;
    system_prompt: string;
};

defineProps<{
    form: InertiaForm<AgentForm>;
    submitLabel: string;
    cancelHref: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form
        class="flex max-w-2xl flex-col gap-5"
        @submit.prevent="emit('submit')"
    >
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
                <Label for="name">Név</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    placeholder="Dokumentáció-író"
                    autocomplete="off"
                />
                <p class="text-xs text-muted-foreground">
                    Olvasható név, ez jelenik meg a felületen.
                </p>
                <InputError :message="form.errors.name" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="slug">Azonosító</Label>
                <Input
                    id="slug"
                    v-model="form.slug"
                    type="text"
                    placeholder="documentarian"
                    autocomplete="off"
                    class="font-mono"
                />
                <p class="text-xs text-muted-foreground">
                    Angol slug (kisbetű, szám, kötőjel) — a definíciós fájl neve
                    és a Claude Code agent neve.
                </p>
                <InputError :message="form.errors.slug" />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <Label for="description"
                >Leírás
                <span class="text-muted-foreground">(opcionális)</span></Label
            >
            <textarea
                id="description"
                v-model="form.description"
                rows="2"
                placeholder="Mire való, mikor hívd ezt az agentet?"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
                <Label for="model"
                    >Modell
                    <span class="text-muted-foreground"
                        >(opcionális)</span
                    ></Label
                >
                <Input
                    id="model"
                    v-model="form.model"
                    type="text"
                    placeholder="claude-opus-4-8"
                    autocomplete="off"
                />
                <InputError :message="form.errors.model" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="tools"
                    >Eszközök
                    <span class="text-muted-foreground"
                        >(vesszővel elválasztva)</span
                    ></Label
                >
                <Input
                    id="tools"
                    v-model="form.tools"
                    type="text"
                    placeholder="Read, Grep, Edit, Write"
                    autocomplete="off"
                />
                <InputError :message="form.errors.tools" />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <Label for="system_prompt">System prompt</Label>
            <textarea
                id="system_prompt"
                v-model="form.system_prompt"
                rows="16"
                placeholder="Az agent instrukciói — ez a lelke."
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
            <InputError :message="form.errors.system_prompt" />
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
