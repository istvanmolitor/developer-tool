<script setup lang="ts">
import { useHttp } from "@inertiajs/vue3";
import {
    File,
    FileArchive,
    FileAudio,
    FileCode,
    FileSpreadsheet,
    FileText,
    FileVideo,
    Image as ImageIcon,
    Loader2,
    Paperclip,
    Upload,
} from "@lucide/vue";
import { type Component, ref, watch } from "vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import projectRoutes from "@/routes/projects";

type PickerAttachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    source: string;
    relative_path: string | null;
    path: string;
    created_at: string | null;
};

const props = defineProps<{
    /** Project the attachments belong to; the button is disabled without one. */
    project: string | null;
}>();

const emit = defineEmits<{
    /** The markdown reference to drop into the prompt. */
    (event: "select", reference: string): void;
}>();

const open = ref(false);
const loading = ref(false);
const loadError = ref<string | null>(null);
const attachments = ref<PickerAttachment[]>([]);

const listHttp = useHttp<Record<string, never>, { attachments: PickerAttachment[] }>({});

async function loadAttachments(): Promise<void> {
    if (!props.project) {
        return;
    }

    loading.value = true;
    loadError.value = null;

    try {
        const response = await listHttp.get(
            projectRoutes.attachments.picker(props.project).url,
        );
        attachments.value = response.attachments;
    } catch {
        loadError.value = "A csatolmányok betöltése nem sikerült.";
    } finally {
        loading.value = false;
    }
}

// Refresh the list each time the modal opens, so new files show up.
watch(open, (isOpen) => {
    if (isOpen) {
        loadAttachments();
    }
});

const uploadHttp = useHttp<{ file: File | null }, { attachment: PickerAttachment }>({
    file: null,
});
const fileInput = ref<HTMLInputElement | null>(null);

async function onFileChange(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (!file || !props.project) {
        return;
    }

    uploadHttp.file = file;

    try {
        // useHttp sends multipart/form-data automatically because `file` is a File.
        const response = await uploadHttp.post(
            projectRoutes.attachments.picker.upload(props.project).url,
        );
        // Show the fresh upload at the top without a round-trip to the list.
        attachments.value = [response.attachment, ...attachments.value];
    } catch {
        // Validation errors land on uploadHttp.errors.file below.
    } finally {
        uploadHttp.reset();
        if (fileInput.value) {
            fileInput.value.value = "";
        }
    }
}

/** Pick a file-type icon from the mime type, so the grid reads at a glance. */
function iconFor(mime: string): Component {
    if (mime.startsWith("image/")) return ImageIcon;
    if (mime.startsWith("audio/")) return FileAudio;
    if (mime.startsWith("video/")) return FileVideo;
    if (mime === "application/pdf") return FileText;
    if (mime.includes("zip") || mime.includes("compressed") || mime.includes("tar"))
        return FileArchive;
    if (
        mime.includes("spreadsheet") ||
        mime.includes("excel") ||
        mime === "text/csv"
    )
        return FileSpreadsheet;
    if (
        mime.includes("json") ||
        mime.includes("xml") ||
        mime.includes("javascript") ||
        mime.startsWith("text/")
    )
        return FileCode;
    return File;
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Build the reference inserted into the prompt. A markdown link whose target is
 * the attachment's absolute path, so the agent can read the file directly.
 */
function select(attachment: PickerAttachment): void {
    emit("select", `[📎 ${attachment.filename}](${attachment.path})`);
    open.value = false;
}
</script>

<template>
    <div>
        <Button
            type="button"
            variant="outline"
            size="icon"
            :disabled="!project"
            :title="
                project
                    ? 'Csatolmány beszúrása'
                    : 'Előbb válassz projektet'
            "
            aria-label="Csatolmány beszúrása"
            @click="open = true"
        >
            <Paperclip class="size-4" />
        </Button>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Csatolmány kiválasztása</DialogTitle>
                    <DialogDescription>
                        Válassz a projekt csatolmányaiból, vagy tölts fel újat. A
                        kiválasztott fájl hivatkozása az üzenetbe kerül, így az
                        agent is eléri.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-4">
                    <div
                        class="flex flex-wrap items-center gap-2 rounded-lg border border-dashed p-3"
                    >
                        <label
                            for="attachment-picker-file"
                            class="text-sm text-muted-foreground"
                        >
                            Új csatolmány:
                        </label>
                        <input
                            id="attachment-picker-file"
                            ref="fileInput"
                            type="file"
                            :disabled="uploadHttp.processing"
                            class="block flex-1 text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent"
                            @change="onFileChange"
                        />
                        <Loader2
                            v-if="uploadHttp.processing"
                            class="size-4 animate-spin text-muted-foreground"
                        />
                        <Upload v-else class="size-4 text-muted-foreground" />
                    </div>
                    <InputError :message="uploadHttp.errors.file" />

                    <div
                        v-if="loading"
                        class="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"
                    >
                        <Loader2 class="size-4 animate-spin" />
                        Betöltés…
                    </div>

                    <p
                        v-else-if="loadError"
                        class="py-10 text-center text-sm text-destructive"
                    >
                        {{ loadError }}
                    </p>

                    <div
                        v-else-if="attachments.length === 0"
                        class="flex flex-col items-center gap-2 py-10 text-center text-sm text-muted-foreground"
                    >
                        <Paperclip class="size-8" />
                        <p>Még nincs csatolmány ebben a projektben.</p>
                        <p class="text-xs">Tölts fel egyet fent.</p>
                    </div>

                    <ul
                        v-else
                        class="grid max-h-80 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3"
                    >
                        <li v-for="attachment in attachments" :key="attachment.id">
                            <button
                                type="button"
                                class="flex w-full items-start gap-2 rounded-lg border p-3 text-left transition-colors hover:border-ring hover:bg-accent"
                                @click="select(attachment)"
                            >
                                <component
                                    :is="iconFor(attachment.mime_type)"
                                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium text-foreground"
                                        :title="attachment.filename"
                                    >
                                        {{ attachment.filename }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatSize(attachment.size) }}
                                    </span>
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
