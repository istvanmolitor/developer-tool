<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import { Download, Paperclip, RefreshCw, Trash2, Upload } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import projects from "@/routes/projects";
import ProjectNav from "../ProjectNav.vue";

type Attachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    source: string;
    relative_path: string | null;
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

const uploadForm = useForm<{ file: File | null }>({ file: null });

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    uploadForm.file = target.files?.[0] ?? null;
}

function submit(): void {
    uploadForm.post(projects.attachments.store(props.project.name).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => uploadForm.reset(),
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
        { preserveScroll: true },
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
</script>

<template>
    <Head :title="`Csatolmányok — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="project.name" :description="project.path" />
            <div class="flex shrink-0 items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="syncForm.processing"
                    @click="sync"
                >
                    <RefreshCw
                        class="size-4"
                        :class="syncForm.processing ? 'animate-spin' : ''"
                    />
                    {{ syncForm.processing ? "Betöltés…" : "Frissítés" }}
                </Button>
            </div>
        </div>

        <ProjectNav :name="project.name" />

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Új csatolmány feltöltése</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-2" @submit.prevent="submit">
                    <label for="attachment-file" class="sr-only">
                        Fájl feltöltése
                    </label>
                    <input
                        id="attachment-file"
                        type="file"
                        class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent"
                        @change="onFileChange"
                    />
                    <InputError :message="uploadForm.errors.file" />
                    <div>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="
                                uploadForm.processing || uploadForm.file === null
                            "
                        >
                            <Upload class="size-4" />
                            Feltöltés
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div
            v-if="attachments.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <Paperclip class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincs csatolmány</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Tölts fel egy fájlt, vagy nyomd meg a
                <strong>Frissítés</strong> gombot a lemezen lévő fájlok
                betöltéséhez.
            </p>
        </div>

        <ul v-else class="flex flex-col divide-y rounded-xl border">
            <li
                v-for="attachment in attachments"
                :key="attachment.id"
                class="flex items-center justify-between gap-3 p-3 text-sm"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <Paperclip class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <span class="block truncate font-medium text-foreground">
                            {{ attachment.relative_path ?? attachment.filename }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ formatSize(attachment.size) }} ·
                            {{
                                sourceLabels[attachment.source] ??
                                attachment.source
                            }}
                            · {{ formatDate(attachment.created_at) }}
                        </span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button variant="ghost" size="sm" as-child>
                        <a :href="downloadUrl(attachment.id)">
                            <Download class="size-4" />
                            <span class="sr-only">Letöltés</span>
                        </a>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="remove(attachment)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Törlés</span>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
