<script setup lang="ts">
import { MessageSquare, MessageSquarePlus, Loader2, Send } from "@lucide/vue";
import { nextTick, onMounted, ref, watch } from "vue";
import AttachmentPicker from "@/components/AttachmentPicker.vue";
import PromptRunMessage from "@/components/PromptRunMessage.vue";
import type { ChatPromptRun } from "@/components/PromptRunMessage.vue";
import { Button } from "@/components/ui/button";

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

function submit(): void {
    if (props.processing || !props.canSubmit) {
        return;
    }

    emit("submit");
}

const promptTextarea = ref<HTMLTextAreaElement | null>(null);

/** Drop an attachment reference into the prompt at the cursor, then refocus. */
function insertReference(reference: string): void {
    const insert = `${reference} `;
    const el = promptTextarea.value;

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
                <div class="flex gap-2">
                    <AttachmentPicker
                        class="self-end"
                        :project="project"
                        @select="insertReference"
                    />
                    <textarea
                        ref="promptTextarea"
                        :value="modelValue"
                        rows="2"
                        aria-label="Üzenet"
                        :placeholder="placeholder"
                        class="min-h-9 w-full flex-1 resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                        @input="
                            emit(
                                'update:modelValue',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                        @keydown="onPromptKeydown"
                    />
                    <Button
                        type="submit"
                        class="self-end"
                        :disabled="processing || !canSubmit"
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
                <div
                    v-if="context"
                    class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                >
                    <span>
                        {{
                            context.session_id
                                ? "Az üzenetek a korábbi beszélgetést folytatják."
                                : "Új beszélgetés — az első üzenet indítja."
                        }}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="startingContext || !context.session_id"
                        @click="emit('start-new-context')"
                    >
                        <MessageSquarePlus class="size-4" />
                        Új beszélgetés
                    </Button>
                </div>
                <slot name="errors" />
            </form>
        </div>
    </div>
</template>
