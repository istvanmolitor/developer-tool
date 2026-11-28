<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Paperclip, Send, X } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import mailboxes from '@/routes/mailboxes';

type Mailbox = {
    id: number;
    name: string;
    email: string;
    from_name: string | null;
};

const props = defineProps<{
    mailbox: Mailbox;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Postafiókok', href: mailboxes.index().url }],
    },
});

const form = useForm<{
    to: string;
    subject: string;
    body: string;
    attachments: File[];
}>({
    to: '',
    subject: '',
    body: '',
    attachments: [],
});

const fileInput = ref<HTMLInputElement | null>(null);

function addFiles(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (input.files) {
        form.attachments = [...form.attachments, ...Array.from(input.files)];
    }

    // Let the same file be picked again after it is removed.
    input.value = '';
}

function removeFile(index: number): void {
    form.attachments = form.attachments.filter((_, i) => i !== index);
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toLocaleString('hu-HU', { maximumFractionDigits: 1 })} ${units[unit]}`;
}

function submit(): void {
    form.post(mailboxes.compose.store(props.mailbox.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Új levél — ${mailbox.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Új levél"
            :description="`Feladó: ${mailbox.from_name ? `${mailbox.from_name} <${mailbox.email}>` : mailbox.email}`"
        />

        <Card class="max-w-3xl">
            <CardContent>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-2">
                        <Label for="to">Címzettek</Label>
                        <Input
                            id="to"
                            v-model="form.to"
                            type="text"
                            placeholder="nev@pelda.hu, masik@pelda.hu"
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            Több címzettet vesszővel válassz el.
                        </p>
                        <InputError :message="form.errors.to" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="subject">Tárgy</Label>
                        <Input
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.subject" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="body">Szöveg</Label>
                        <textarea
                            id="body"
                            v-model="form.body"
                            rows="12"
                            class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-neutral-900 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-neutral-500 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError :message="form.errors.body" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label>Csatolmányok</Label>
                        <div>
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                class="sr-only"
                                @change="addFiles"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="fileInput?.click()"
                            >
                                <Paperclip class="size-4" />
                                Fájl hozzáadása
                            </Button>
                        </div>
                        <ul
                            v-if="form.attachments.length > 0"
                            class="flex flex-wrap gap-2"
                        >
                            <li
                                v-for="(file, index) in form.attachments"
                                :key="index"
                                class="flex max-w-xs items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs"
                            >
                                <Paperclip
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ file.name }}</span>
                                <span class="shrink-0 text-muted-foreground">{{
                                    formatSize(file.size)
                                }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-full text-muted-foreground hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    :aria-label="`${file.name} eltávolítása`"
                                    @click="removeFile(index)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </li>
                        </ul>
                        <InputError :message="form.errors.attachments" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button type="submit" :disabled="form.processing">
                            <Send class="size-4" />
                            {{ form.processing ? 'Küldés…' : 'Küldés' }}
                        </Button>
                        <Button type="button" variant="ghost" as-child>
                            <a :href="mailboxes.show(mailbox.id).url">Mégse</a>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
