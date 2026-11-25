<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { Download, Paperclip, Trash2, Upload } from "@lucide/vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import projects from "@/routes/projects";

export type TaskAttachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    source: string;
    created_at: string | null;
};

const props = defineProps<{
    attachments: TaskAttachment[];
    projectName: string;
    taskId: number;
}>();

const form = useForm<{ file: File | null }>({ file: null });

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit(): void {
    form.post(
        projects.tasks.attachments.store({
            project: props.projectName,
            task: props.taskId,
        }).url,
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.reset(),
        },
    );
}

function downloadUrl(attachmentId: number): string {
    return projects.tasks.attachments.download({
        project: props.projectName,
        task: props.taskId,
        attachment: attachmentId,
    }).url;
}

function remove(attachmentId: number): void {
    router.delete(
        projects.tasks.attachments.destroy({
            project: props.projectName,
            task: props.taskId,
            attachment: attachmentId,
        }).url,
        { preserveScroll: true },
    );
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const sourceLabels: Record<string, string> = {
    upload: "Feltöltött",
    sync: "Mappából",
    mail: "Levélből",
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <ul
            v-if="attachments.length > 0"
            class="flex flex-col divide-y rounded-xl border"
        >
            <li
                v-for="attachment in attachments"
                :key="attachment.id"
                class="flex items-center justify-between gap-3 p-3 text-sm"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <Paperclip class="size-4 shrink-0 text-muted-foreground" />
                    <span class="truncate font-medium text-foreground">
                        {{ attachment.filename }}
                    </span>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ formatSize(attachment.size) }} ·
                        {{ sourceLabels[attachment.source] ?? attachment.source }}
                    </span>
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
                        @click="remove(attachment.id)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Törlés</span>
                    </Button>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            Még nincs csatolmány.
        </p>

        <form class="flex flex-col gap-2" @submit.prevent="submit">
            <label for="attachment-file" class="sr-only">Fájl feltöltése</label>
            <input
                id="attachment-file"
                type="file"
                class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent"
                @change="onFileChange"
            />
            <InputError :message="form.errors.file" />
            <div>
                <Button
                    type="submit"
                    size="sm"
                    :disabled="form.processing || form.file === null"
                >
                    <Upload class="size-4" />
                    Feltöltés
                </Button>
            </div>
        </form>
    </div>
</template>
