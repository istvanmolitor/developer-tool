<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import type { AgentToolGroup } from '@/types';
import { FileText, KeyRound, Plug } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AgentToolPicker from './AgentToolPicker.vue';

type AgentForm = {
    name: string;
    slug: string;
    description: string;
    model: string;
    tools: string[];
    system_prompt: string;
};

const props = defineProps<{
    form: InertiaForm<AgentForm>;
    toolGroups: AgentToolGroup[];
    submitLabel: string;
    cancelHref: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const tab = ref<'general' | 'permissions' | 'mcp'>('general');

/** Claude Code's own tools and this application's MCP tools, each on its own tab. */
const builtinGroups = computed(() =>
    props.toolGroups.filter((group) => group.kind === 'builtin'),
);

const mcpGroups = computed(() =>
    props.toolGroups.filter((group) => group.kind === 'mcp'),
);

function selectedIn(groups: AgentToolGroup[]): number {
    return groups.reduce(
        (count, group) =>
            count + group.tools.filter((tool) => isSelected(tool.name)).length,
        0,
    );
}

const tabs = computed(() => [
    {
        key: 'general' as const,
        title: 'Alapadatok',
        icon: FileText,
        badge: null as string | null,
        hasError: Boolean(
            props.form.errors.name ||
            props.form.errors.slug ||
            props.form.errors.description ||
            props.form.errors.model ||
            props.form.errors.system_prompt,
        ),
    },
    {
        key: 'permissions' as const,
        title: 'Jogosultságok',
        icon: KeyRound,
        badge:
            props.form.tools.length === 0
                ? 'mind'
                : String(selectedIn(builtinGroups.value)),
        hasError: false,
    },
    {
        key: 'mcp' as const,
        title: 'MCP',
        icon: Plug,
        badge:
            props.form.tools.length === 0
                ? 'mind'
                : String(selectedIn(mcpGroups.value)),
        hasError: Boolean(props.form.errors.tools),
    },
]);

// A failed submit may be about a field on the other tab: open the tab that failed.
watch(
    () => props.form.errors,
    () => {
        const failing = tabs.value.find((item) => item.hasError);

        if (failing !== undefined) {
            tab.value = failing.key;
        }
    },
    { deep: true },
);

const knownTools = computed(
    () =>
        new Set(
            [...builtinGroups.value, ...mcpGroups.value].flatMap((group) =>
                group.tools.map((tool) => tool.name),
            ),
        ),
);

/** Tools the agent has that are not in the catalogue, as free text. */
const extraTools = ref(
    props.form.tools.filter((tool) => !knownTools.value.has(tool)).join(', '),
);

watch(extraTools, (value) => {
    const extras = value
        .split(',')
        .map((tool) => tool.trim())
        .filter((tool) => tool !== '');

    props.form.tools = [
        ...props.form.tools.filter((tool) => knownTools.value.has(tool)),
        ...extras,
    ];
});

function isSelected(name: string): boolean {
    return props.form.tools.includes(name);
}
</script>

<template>
    <form class="flex w-full flex-col gap-5" @submit.prevent="emit('submit')">
        <nav class="flex items-center gap-1 border-b">
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                :class="
                    tab === item.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="tab = item.key"
            >
                <component :is="item.icon" class="size-4" />
                {{ item.title }}
                <span
                    v-if="item.badge"
                    class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground"
                    >{{ item.badge }}</span
                >
                <span
                    v-if="item.hasError"
                    class="size-1.5 rounded-full bg-destructive"
                    aria-hidden="true"
                />
            </button>
        </nav>

        <div v-show="tab === 'general'" class="flex flex-col gap-5">
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
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
                        Angol slug (kisbetű, szám, kötőjel) — a definíciós fájl
                        neve és a Claude Code agent neve.
                    </p>
                    <InputError :message="form.errors.slug" />
                </div>

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
                    <p class="text-xs text-muted-foreground">
                        Üresen az örökölt alapértelmezés fut.
                    </p>
                    <InputError :message="form.errors.model" />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <Label for="description"
                    >Leírás
                    <span class="text-muted-foreground"
                        >(opcionális)</span
                    ></Label
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

            <div class="flex flex-col gap-2">
                <Label for="system_prompt">System prompt</Label>
                <textarea
                    id="system_prompt"
                    v-model="form.system_prompt"
                    rows="20"
                    placeholder="Az agent instrukciói — ez a lelke."
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
                <InputError :message="form.errors.system_prompt" />
            </div>
        </div>

        <div v-show="tab === 'permissions'" class="flex flex-col gap-4">
            <AgentToolPicker v-model="form.tools" :groups="builtinGroups" />
        </div>

        <div v-show="tab === 'mcp'" class="flex flex-col gap-4">
            <p class="text-sm text-muted-foreground">
                A developer-tool MCP szervereinek eszközei. Egy szerver csak
                akkor indul el a futáshoz, ha az agent legalább egy eszközt
                megkap belőle. A levelezés nem itt dől el: levélre az az agent
                tud válaszolni, amelyikhez postafiók van rendelve.
            </p>

            <AgentToolPicker
                v-model="form.tools"
                :groups="mcpGroups"
                :show-tool-names="false"
            />

            <div class="flex max-w-2xl flex-col gap-2">
                <Label for="extra-tools"
                    >Egyéb eszközök
                    <span class="text-muted-foreground"
                        >(vesszővel elválasztva)</span
                    ></Label
                >
                <Input
                    id="extra-tools"
                    v-model="extraTools"
                    type="text"
                    placeholder="mcp__laravel-boost__database-query"
                    autocomplete="off"
                    class="font-mono"
                />
                <p class="text-xs text-muted-foreground">
                    A listán kívüli eszközök (pl. egy másik MCP szerver
                    eszközei) — pontosan úgy, ahogy a Claude Code nevezi őket.
                </p>
                <InputError :message="form.errors.tools" />
            </div>
        </div>

        <div class="flex items-center gap-2 border-t pt-4">
            <Button type="submit" :disabled="form.processing">{{
                submitLabel
            }}</Button>
            <Button type="button" variant="ghost" as-child>
                <a :href="cancelHref">Mégse</a>
            </Button>
        </div>
    </form>
</template>
