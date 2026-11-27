<script setup lang="ts">
import {
    MessageSquare,
    MessageSquarePlus,
    Loader2,
    Maximize2,
    Mic,
    Send,
} from "@lucide/vue";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import AttachmentPicker from "@/components/AttachmentPicker.vue";
import PromptRunMessage from "@/components/PromptRunMessage.vue";
import type { ChatPromptRun } from "@/components/PromptRunMessage.vue";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { useSpeechRecognition } from "@/composables/useSpeechRecognition";

const props = withDefaults(
    defineProps<{
        /** The prompt text (v-model). */
        modelValue: string;
        /** The conversation messages, oldest first. */
        messages: ChatPromptRun[];
        /** Project the attachments belong to and the run is queued on. */
        project: string | null;
        /** The context the chat continues, if it exists yet. */
        context?: { session_id: string | null } | null;
        processing?: boolean;
        /** Whether the send button may fire (e.g. a project is selected). */
        canSubmit?: boolean;
        /** Whether the composer form is shown at all. */
        canCompose?: boolean;
        startingContext?: boolean;
        /** Draw an "Új beszélgetés" divider where the context changes. */
        showContextDividers?: boolean;
        emptyTitle: string;
        emptyHint?: string;
        placeholder: string;
        /** Height classes for the scrollable message list. */
        listClass?: string;
    }>(),
    {
        context: null,
        processing: false,
        canSubmit: true,
        canCompose: true,
        startingContext: false,
        showContextDividers: false,
        emptyHint: "",
        listClass: "min-h-0 flex-1",
    },
);

const emit = defineEmits<{
    (event: "update:modelValue", value: string): void;
    (event: "submit"): void;
    (event: "start-new-context"): void;
}>();

/** Whether a run opens a new conversation compared to the run before it. */
function startsNewContext(index: number): boolean {
    return (
        index > 0 &&
        props.messages[index].agent_context_id !==
            props.messages[index - 1].agent_context_id
    );
}

/** Whether the prompt holds any non-whitespace text to send. */
const hasText = computed(() => props.modelValue.trim().length > 0);

/** Whether the send button may actually fire right now. */
const canSend = computed(
    () => !props.processing && props.canSubmit && hasText.value,
);

function submit(): void {
    if (!canSend.value) {
        return;
    }

    emit("submit");
    expanded.value = false;
}

/** Open the large editor and move focus into its textarea. */
function openExpanded(): void {
    expanded.value = true;
    nextTick(() => expandedTextarea.value?.focus());
}

const promptTextarea = ref<HTMLTextAreaElement | null>(null);
const expandedTextarea = ref<HTMLTextAreaElement | null>(null);

/** Whether the large, near-fullscreen editor is open. */
const expanded = ref(false);

/** Whether the "start a new conversation" confirmation dialog is open. */
const confirmingNewContext = ref(false);

/** Confirm the new conversation and close the dialog. */
function confirmStartNewContext(): void {
    confirmingNewContext.value = false;
    emit("start-new-context");
}

/** The textarea the user is currently composing in. */
const activeTextarea = computed(() =>
    expanded.value ? expandedTextarea.value : promptTextarea.value,
);

/** Drop an attachment reference into the prompt at the cursor, then refocus. */
function insertReference(reference: string): void {
    const insert = `${reference} `;
    const el = activeTextarea.value;

    if (!el) {
        emit(
            "update:modelValue",
            props.modelValue + (props.modelValue ? " " : "") + insert,
        );

        return;
    }

    const start = el.selectionStart ?? props.modelValue.length;
    const end = el.selectionEnd ?? props.modelValue.length;
    emit(
        "update:modelValue",
        props.modelValue.slice(0, start) + insert + props.modelValue.slice(end),
    );

    nextTick(() => {
        const caret = start + insert.length;
        el.focus();
        el.setSelectionRange(caret, caret);
    });
}

/** Enter sends the message, Shift+Enter adds a new line. */
function onPromptKeydown(event: KeyboardEvent): void {
    if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        submit();
    }
}

/** Live, not-yet-final speech guess shown while dictating. */
const interimTranscript = ref("");

/** Append a dictated chunk to the prompt, separated by a space when needed. */
function appendTranscript(text: string): void {
    if (!text) {
        return;
    }

    const current = props.modelValue;
    const separator = current && !/\s$/.test(current) ? " " : "";
    emit("update:modelValue", current + separator + text);
}

const {
    supported: dictationSupported,
    listening: dictating,
    toggle: toggleDictation,
} = useSpeechRecognition({
    lang: "hu-HU",
    onFinal: (text) => {
        appendTranscript(text);
        interimTranscript.value = "";
    },
    onInterim: (text) => {
        interimTranscript.value = text;
    },
});

const messageList = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    nextTick(() => {
        messageList.value?.scrollTo({ top: messageList.value.scrollHeight });
    });
}

onMounted(scrollToBottom);

watch(
    () => props.messages.map((run) => `${run.id}:${run.status}`).join(","),
    scrollToBottom,
);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div
            ref="messageList"
            class="flex flex-col gap-4 overflow-y-auto p-4"
            :class="listClass"
        >
            <div
                v-if="messages.length === 0"
                class="m-auto flex flex-col items-center gap-2 text-center text-sm text-muted-foreground"
            >
                <MessageSquare class="size-8" />
                <p>{{ emptyTitle }}</p>
                <p v-if="emptyHint" class="text-xs">{{ emptyHint }}</p>
            </div>

            <template v-for="(run, index) in messages" :key="run.id">
                <div
                    v-if="showContextDividers && startsNewContext(index)"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <div class="h-px flex-1 bg-border" />
                    Új beszélgetés
                    <div class="h-px flex-1 bg-border" />
                </div>
                <PromptRunMessage :run="run">
                    <template v-if="$slots['message-meta']" #meta>
                        <slot name="message-meta" :run="run" />
                    </template>
                </PromptRunMessage>
            </template>
        </div>

        <div class="border-t bg-background p-3">
            <slot v-if="!canCompose" name="composer-disabled" />
            <form v-else class="flex flex-col gap-2" @submit.prevent="submit">
                <slot name="selectors" />
                <div class="relative">
                    <textarea
                        ref="promptTextarea"
                        :value="modelValue"
                        rows="2"
                        aria-label="Üzenet"
                        :placeholder="placeholder"
                        class="min-h-9 w-full resize-y rounded-md border border-input bg-white py-2 pr-9 pl-3 text-sm text-neutral-900 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-neutral-500 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        @input="
                            emit(
                                'update:modelValue',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                        @keydown="onPromptKeydown"
                    />
                    <button
                        type="button"
                        aria-label="Nagy szerkesztő megnyitása"
                        title="Nagy szerkesztő"
                        class="absolute top-1.5 right-1.5 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                        @click="openExpanded"
                    >
                        <Maximize2 class="size-4" />
                    </button>
                </div>
                <p
                    v-if="dictating"
                    class="px-1 text-xs text-muted-foreground"
                >
                    <span class="text-destructive">●</span>
                    Beszéd felvétele…
                    <span v-if="interimTranscript" class="italic">
                        {{ interimTranscript }}
                    </span>
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="context"
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="startingContext || !context.session_id"
                        @click="confirmingNewContext = true"
                    >
                        <MessageSquarePlus class="size-4" />
                        Új beszélgetés
                    </Button>
                    <AttachmentPicker
                        :project="project"
                        @select="insertReference"
                    />
                    <Button
                        v-if="dictationSupported"
                        type="button"
                        variant="outline"
                        size="icon"
                        :aria-label="
                            dictating
                                ? 'Diktálás leállítása'
                                : 'Diktálás indítása'
                        "
                        :aria-pressed="dictating"
                        :class="dictating ? 'text-destructive' : ''"
                        @click="toggleDictation"
                    >
                        <Mic
                            class="size-4"
                            :class="dictating ? 'animate-pulse' : ''"
                        />
                    </Button>
                    <Button
                        type="submit"
                        class="ml-auto"
                        :disabled="!canSend"
                        aria-label="Küldés"
                    >
                        <Loader2
                            v-if="processing"
                            class="size-4 animate-spin"
                        />
                        <Send v-else class="size-4" />
                        Küldés
                    </Button>
                </div>
                <slot name="errors" />
            </form>
        </div>

        <Dialog v-model:open="confirmingNewContext">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Új beszélgetés indítása</DialogTitle>
                    <DialogDescription>
                        Biztosan új beszélgetést szeretnél kezdeni? A jelenlegi
                        beszélgetés addigi előzménye nem folytatódik tovább.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="confirmingNewContext = false"
                    >
                        Mégse
                    </Button>
                    <Button
                        type="button"
                        :disabled="startingContext"
                        @click="confirmStartNewContext"
                    >
                        <MessageSquarePlus class="size-4" />
                        Új beszélgetés
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="expanded">
            <DialogContent
                class="flex h-[calc(100%-2rem)] max-h-[calc(100%-2rem)] w-[calc(100%-2rem)] max-w-[calc(100%-2rem)] flex-col gap-3 sm:max-w-[calc(100%-2rem)]"
            >
                <DialogHeader>
                    <DialogTitle>Üzenet írása</DialogTitle>
                </DialogHeader>

                <textarea
                    ref="expandedTextarea"
                    :value="modelValue"
                    aria-label="Üzenet"
                    :placeholder="placeholder"
                    class="min-h-0 w-full flex-1 resize-none rounded-md border border-input bg-white px-3 py-2 text-sm text-neutral-900 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-neutral-500 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    @input="
                        emit(
                            'update:modelValue',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                    @keydown="onPromptKeydown"
                />

                <p
                    v-if="dictating"
                    class="text-xs text-muted-foreground"
                >
                    <span class="text-destructive">●</span>
                    Beszéd felvétele…
                    <span v-if="interimTranscript" class="italic">
                        {{ interimTranscript }}
                    </span>
                </p>

                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <AttachmentPicker
                            :project="project"
                            @select="insertReference"
                        />
                        <Button
                            v-if="dictationSupported"
                            type="button"
                            variant="outline"
                            size="icon"
                            :aria-label="
                                dictating
                                    ? 'Diktálás leállítása'
                                    : 'Diktálás indítása'
                            "
                            :aria-pressed="dictating"
                            :class="dictating ? 'text-destructive' : ''"
                            @click="toggleDictation"
                        >
                            <Mic
                                class="size-4"
                                :class="dictating ? 'animate-pulse' : ''"
                            />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        :disabled="!canSend"
                        aria-label="Küldés"
                        @click="submit"
                    >
                        <Loader2
                            v-if="processing"
                            class="size-4 animate-spin"
                        />
                        <Send v-else class="size-4" />
                        Küldés
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
