<script setup lang="ts">
import { computed } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { FolderGit2, FolderPlus, FolderSearch } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type ProjectMode = 'existing' | 'new' | 'git';

type CreateForm = {
    mode: ProjectMode;
    directory: string;
    name: string;
    git_url: string;
    description: string;
};

const props = defineProps<{
    form: InertiaForm<CreateForm>;
    projectsPath: string;
    directories: string[];
    cancelHref: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const modes: Array<{
    value: ProjectMode;
    label: string;
    hint: string;
    icon: typeof FolderSearch;
}> = [
    {
        value: 'existing',
        label: 'Meglévő mappa',
        hint: 'Egy már létező mappa a projektek könyvtárából.',
        icon: FolderSearch,
    },
    {
        value: 'new',
        label: 'Új mappa',
        hint: 'Egy még nem létező mappa — létrejön.',
        icon: FolderPlus,
    },
    {
        value: 'git',
        label: 'Git repó',
        hint: 'Egy repó linkje — git clone-nal jön létre.',
        icon: FolderGit2,
    },
];

function selectMode(mode: ProjectMode): void {
    props.form.mode = mode;
    props.form.clearErrors();
}

// Preview the folder name a git link would resolve to, so the user sees what
// the directory will be called before overriding it.
const derivedGitName = computed<string>(() => {
    const url = props.form.git_url.trim();
    if (!url) {
        return '';
    }

    const trimmed = url.replace(/\/+$/, '').replace(/\.git$/i, '');
    const segment = trimmed.split(/[/:]/).pop() ?? '';

    return segment;
});

const activeHint = computed<string>(
    () => modes.find((m) => m.value === props.form.mode)?.hint ?? '',
);
</script>

<template>
    <form
        class="flex max-w-2xl flex-col gap-6"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-2">
            <Label>Hogyan hozzuk létre?</Label>
            <div class="grid gap-2 sm:grid-cols-3">
                <button
                    v-for="mode in modes"
                    :key="mode.value"
                    type="button"
                    class="flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors hover:border-primary/50"
                    :class="
                        form.mode === mode.value
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : 'border-input'
                    "
                    @click="selectMode(mode.value)"
                >
                    <component
                        :is="mode.icon"
                        class="size-4 text-muted-foreground"
                    />
                    <span class="text-sm font-medium">{{ mode.label }}</span>
                </button>
            </div>
            <p class="text-xs text-muted-foreground">{{ activeHint }}</p>
            <InputError :message="form.errors.mode" />
        </div>

        <!-- Existing folder: pick from the dropdown of unregistered dirs. -->
        <div v-if="form.mode === 'existing'" class="flex flex-col gap-2">
            <Label for="directory">Mappa</Label>
            <Select v-model="form.directory">
                <SelectTrigger id="directory" class="w-full">
                    <SelectValue placeholder="Válassz egy mappát…" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="dir in directories"
                        :key="dir"
                        :value="dir"
                    >
                        {{ dir }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p
                v-if="directories.length === 0"
                class="text-xs text-muted-foreground"
            >
                Nincs szabad mappa a(z)
                <code class="rounded bg-muted px-1 py-0.5">{{
                    projectsPath
                }}</code>
                könyvtárban — minden alkönyvtár már regisztrálva van.
            </p>
            <p v-else class="text-xs text-muted-foreground">
                A(z)
                <code class="rounded bg-muted px-1 py-0.5">{{
                    projectsPath
                }}</code>
                alatti, még nem regisztrált mappák.
            </p>
            <InputError :message="form.errors.directory" />
        </div>

        <!-- New folder: just the folder name; it is created on disk. -->
        <div v-else-if="form.mode === 'new'" class="flex flex-col gap-2">
            <Label for="name">Mappa neve</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="my-project"
                autocomplete="off"
            />
            <p class="text-xs text-muted-foreground">
                Létrejön:
                <code class="rounded bg-muted px-1 py-0.5"
                    >{{ projectsPath }}/{{ form.name || 'my-project' }}</code
                >
            </p>
            <InputError :message="form.errors.name" />
        </div>

        <!-- Git link: URL + optional folder name override. -->
        <template v-else>
            <div class="flex flex-col gap-2">
                <Label for="git_url"
                    >Git link
                    <span class="text-muted-foreground"
                        >(opcionális)</span
                    ></Label
                >
                <Input
                    id="git_url"
                    v-model="form.git_url"
                    type="text"
                    placeholder="https://github.com/user/repo.git"
                    autocomplete="off"
                />
                <p class="text-xs text-muted-foreground">
                    A tároló URL-je (HTTPS vagy SSH). Megadva a mappa git
                    clone-nal jön létre; üresen hagyva üres mappa készül.
                </p>
                <InputError :message="form.errors.git_url" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="name"
                    >Mappa neve
                    <span class="text-muted-foreground"
                        >(opcionális)</span
                    ></Label
                >
                <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    :placeholder="derivedGitName || 'a-repó-nevéből'"
                    autocomplete="off"
                />
                <p class="text-xs text-muted-foreground">
                    Üresen hagyva a repó nevéből képződik<template
                        v-if="derivedGitName"
                        >:
                        <code class="rounded bg-muted px-1 py-0.5">{{
                            derivedGitName
                        }}</code></template
                    >.
                </p>
                <InputError :message="form.errors.name" />
            </div>
        </template>

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
            <Button type="submit" :disabled="form.processing">
                Projekt létrehozása
            </Button>
            <Button type="button" variant="ghost" as-child>
                <a :href="cancelHref">Mégse</a>
            </Button>
        </div>
    </form>
</template>
