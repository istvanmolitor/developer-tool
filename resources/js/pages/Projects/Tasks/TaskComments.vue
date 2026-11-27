<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Bot, MessageSquare, User } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import projects from '@/routes/projects';

export type TaskComment = {
    id: number;
    body: string;
    created_at: string | null;
    author: { type: 'user' | 'agent' | null; name: string | null };
};

const props = defineProps<{
    comments: TaskComment[];
    projectName: string;
    taskId: number;
}>();

const form = useForm({ body: '' });

function submit(): void {
    form.post(
        projects.tasks.comments.store({
            project: props.projectName,
            task: props.taskId,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        },
    );
}

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}

function authorName(author: TaskComment['author']): string {
    if (author.type === null) return 'Ismeretlen (MCP)';
    return (
        author.name ??
        (author.type === 'agent' ? 'Törölt agent' : 'Törölt felhasználó')
    );
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <ul
            v-if="comments.length > 0"
            class="flex flex-col divide-y rounded-xl border"
        >
            <li
                v-for="comment in comments"
                :id="`comment-${comment.id}`"
                :key="comment.id"
                class="flex flex-col gap-2 p-4 text-sm"
            >
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground"
                >
                    <span
                        class="flex items-center gap-1 font-medium text-foreground"
                    >
                        <Bot
                            v-if="comment.author.type === 'agent'"
                            class="size-4"
                        />
                        <User v-else class="size-4" />
                        {{ authorName(comment.author) }}
                    </span>
                    <span>{{ formatDateTime(comment.created_at) }}</span>
                </div>
                <p class="whitespace-pre-wrap text-foreground">
                    {{ comment.body }}
                </p>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            Még nincs hozzászólás.
        </p>

        <form class="flex flex-col gap-2" @submit.prevent="submit">
            <label for="comment-body" class="sr-only">Új hozzászólás</label>
            <textarea
                id="comment-body"
                v-model="form.body"
                rows="3"
                placeholder="Írj hozzászólást…"
                class="w-full rounded-md border border-input bg-white text-neutral-900 px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-neutral-500 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="form.errors.body" />
            <div>
                <Button
                    type="submit"
                    size="sm"
                    :disabled="form.processing || form.body.trim() === ''"
                >
                    <MessageSquare class="size-4" />
                    Hozzászólás küldése
                </Button>
            </div>
        </form>
    </div>
</template>
