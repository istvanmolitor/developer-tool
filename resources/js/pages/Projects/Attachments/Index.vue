<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import {
    Download,
    File,
    FileArchive,
    FileCode,
    FileSpreadsheet,
    FileText,
    Image,
    Info,
    Paperclip,
    RefreshCw,
    Trash2,
    Upload,
} from "@lucide/vue";
import { ref } from "vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import projects from "@/routes/projects";
import ProjectNav from "../ProjectNav.vue";

type Attachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    source: string;
    relative_path: string | null;
    uploaded_by: string | null;
    created_at: string | null;
};

type Project = {
    id: number;
    name: string;
    path: string;
};

const props = defineProps<{
    project: Project;
    attachments: Attachment[];
    attachments_path: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

const uploadOpen = ref(false);
const selected = ref<Attachment | null>(null);

const uploadForm = useForm<{ file: File | null }>({ file: null });

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    uploadForm.file = target.files?.[0] ?? null;
}

function submit(): void {
    uploadForm.post(projects.attachments.store(props.project.name).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            uploadOpen.value = false;
        },
    });
}

const syncForm = useForm({});

function sync(): void {
    syncForm.post(projects.attachments.sync(props.project.name).url, {
        preserveScroll: true,
    });
}

function downloadUrl(attachmentId: number): string {
    return projects.attachments.download({
        project: props.project.name,
        attachment: attachmentId,
    }).url;
}

function remove(attachment: Attachment): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${attachment.filename}” csatolmányt? A lemezen lévő fájl is törlődik.`,
        )
    ) {
        return;
    }

    router.delete(
        projects.attachments.destroy({
            project: props.project.name,
            attachment: attachment.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
        },
    );
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}

const sourceLabels: Record<string, string> = {
    upload: "Feltöltött",
    sync: "Mappából",
    mail: "Levélből",
};

/** The icon that stands for the file's type in the grid. */
function iconFor(attachment: Attachment) {
    const type = attachment.mime_type;

    if (type.startsWith("image/")) return Image;
    if (type === "application/pdf") return FileText;
    if (type.includes("zip") || type.includes("tar") || type.includes("rar"))
        return FileArchive;
    if (type.includes("sheet") || type.includes("csv")) return FileSpreadsheet;
    if (
        type.startsWith("text/") ||
        type.includes("json") ||
        type.includes("xml") ||
        type.includes("script")
    )
        return FileCode;

    return File;
}
</script>

<template>
    <Head :title="`Csatolmányok — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="project.name"
                description="A projekthez tárolt fájlok, amelyekre az agentek hivatkozhatnak."
            />
            <div class="flex shrink-0 items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="syncForm.processing"
                    title="Az attachments mappa betöltése"
                    @click="sync"
                >
                    <RefreshCw
                        class="size-4"
                        :class="syncForm.processing ? 'animate-spin' : ''"
                    />
                    {{ syncForm.processing ? "Betöltés…" : "Frissítés" }}
                </Button>
                <Button size="sm" @click="uploadOpen = true">
                    <Upload class="size-4" />
                    Feltöltés
                </Button>
            </div>
        </div>

        <ProjectNav :name="project.name" />

        <div
            v-if="attachments.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <Paperclip class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincs csatolmány</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Tölts fel egy fájlt, vagy olvasd be a mappát a
                <strong>Frissítés</strong> gombbal.
            </p>
        </div>

        <ul
            v-else
            class="grid auto-rows-min gap-3 grid-cols-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8"
        >
            <li v-for="attachment in attachments" :key="attachment.id">
                <div
                    class="group relative flex h-full flex-col items-center gap-2 rounded-xl border p-3 text-center transition-colors hover:border-primary/50"
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="absolute top-1 right-1 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                        :title="`${attachment.filename} részletei`"
                        :aria-label="`${attachment.filename} részletei`"
                        @click="selected = attachment"
                    >
                        <Info class="size-4" />
                    </Button>

                    <a
                        :href="downloadUrl(attachment.id)"
                        class="flex w-full flex-col items-center gap-2 outline-none"
                        :title="attachment.relative_path ?? attachment.filename"
                    >
                        <component
                            :is="iconFor(attachment)"
                            class="size-10 text-muted-foreground"
                        />
                        <span
                            class="line-clamp-2 w-full text-xs leading-tight break-words group-hover:underline"
                        >
                            {{ attachment.filename }}
                        </span>
                    </a>
                </div>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="uploadOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Csatolmány feltöltése</DialogTitle>
                <DialogDescription>
                    A fájl a projekt csatolmány-mappájába kerül.
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-3" @submit.prevent="submit">
                <label for="attachment-file" class="sr-only">
                    Fájl feltöltése
                </label>
                <input
                    id="attachment-file"
                    type="file"
                    class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent"
                    @change="onFileChange"
                />
                <InputError :message="uploadForm.errors.file" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="uploadOpen = false"
                    >
                        Mégse
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            uploadForm.processing || uploadForm.file === null
                        "
                    >
                        <Upload class="size-4" />
                        Feltöltés
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="selected !== null"
        @update:open="(open) => !open && (selected = null)"
    >
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="break-all">
                    {{ selected?.filename }}
                </DialogTitle>
                <DialogDescription>A fájl részletei.</DialogDescription>
            </DialogHeader>

            <dl v-if="selected" class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">
                        Típus
                    </dt>
                    <dd class="mt-1 font-mono break-all">
                        {{ selected.mime_type }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">
                        Méret
                    </dt>
                    <dd class="mt-1">{{ formatSize(selected.size) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">
                        Feltöltő
                    </dt>
                    <dd class="mt-1">
                        {{
                            selected.uploaded_by ??
                            sourceLabels[selected.source] ??
                            selected.source
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">
                        Forrás
                    </dt>
                    <dd class="mt-1">
                        {{ sourceLabels[selected.source] ?? selected.source }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">
                        Feltöltve
                    </dt>
                    <dd class="mt-1">{{ formatDate(selected.created_at) }}</dd>
                </div>
                <div v-if="selected.relative_path" class="sm:col-span-2">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Útvonal a mappában
                    </dt>
                    <dd class="mt-1 font-mono break-all">
                        {{ selected.relative_path }}
                    </dd>
                </div>
            </dl>

            <DialogFooter>
                <Button
                    v-if="selected"
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    @click="remove(selected)"
                >
                    <Trash2 class="size-4" />
                    Törlés
                </Button>
                <Button v-if="selected" variant="outline" as-child>
                    <a :href="downloadUrl(selected.id)">
                        <Download class="size-4" />
                        Letöltés
                    </a>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
