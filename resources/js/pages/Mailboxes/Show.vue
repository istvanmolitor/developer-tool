<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import {
    BookOpenCheck,
    Download,
    Inbox,
    Paperclip,
    Pencil,
    Reply as ReplyIcon,
    Send,
    Trash2,
} from "@lucide/vue";
import { computed, ref } from "vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import agents from "@/routes/agents";
import mailboxes from "@/routes/mailboxes";
import projects from "@/routes/projects";

type Mailbox = {
    id: number;
    name: string;
    email: string;
    from_name: string | null;
    username: string;
    imap_host: string;
    imap_port: number;
    imap_encryption: string;
    imap_folder: string;
    smtp_host: string;
    smtp_port: number;
    smtp_encryption: string;
    is_active: boolean;
    last_fetched_at: string | null;
    auto_read: boolean;
    auto_reply: boolean;
    agent: { id: number; name: string } | null;
    project: { id: number; name: string } | null;
};

type Attachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
};

type Reply = {
    id: number;
    to: string[];
    subject: string;
    body_text: string;
    status: "draft" | "sent" | "failed";
    error: string | null;
    sent_at: string | null;
    created_at: string;
};

type IncomingEmail = {
    id: number;
    from_address: string | null;
    from_name: string | null;
    subject: string | null;
    body_text: string | null;
    received_at: string | null;
    read_at: string | null;
    attachments: Attachment[];
    prompt_run: { id: number; status: string } | null;
    replies: Reply[];
};

const props = defineProps<{
    mailbox: Mailbox;
    incomingEmails: IncomingEmail[];
    incomingEmailsCount: number;
    unreadEmailsCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Postafiókok", href: mailboxes.index().url }],
    },
});

const fetching = ref(false);
const openEmailId = ref<number | null>(null);
const readingEmailId = ref<number | null>(null);
const sendingReplyId = ref<number | null>(null);

const canAgentRead = computed(
    () => props.mailbox.agent !== null && props.mailbox.project !== null,
);

const promptRunStatusLabels: Record<string, string> = {
    pending: "sorban áll",
    running: "fut",
    completed: "kész",
    failed: "sikertelen",
};

const replyStatusLabels: Record<Reply["status"], string> = {
    draft: "Piszkozat — jóváhagyásra vár",
    sent: "Elküldve",
    failed: "A küldés nem sikerült",
};

function hasPendingReply(email: IncomingEmail): boolean {
    return email.replies.some((reply) => reply.status !== "sent");
}

/**
 * Approve the agent's reply: send it (or retry a failed send) from the mailbox.
 */
function sendReply(reply: Reply): void {
    router.post(
        mailboxes.replies.send({
            mailbox: props.mailbox.id,
            outgoingEmail: reply.id,
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (sendingReplyId.value = reply.id),
            onFinish: () => (sendingReplyId.value = null),
        },
    );
}

function discardReply(reply: Reply): void {
    if (
        !window.confirm(
            "Elveted az agent válaszát? A levél nem megy ki a feladónak.",
        )
    ) {
        return;
    }

    router.delete(
        mailboxes.replies.destroy({
            mailbox: props.mailbox.id,
            outgoingEmail: reply.id,
        }).url,
        { preserveScroll: true, preserveState: true },
    );
}

function fetchMail(): void {
    router.post(
        mailboxes.fetch(props.mailbox.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (fetching.value = true),
            onFinish: () => (fetching.value = false),
        },
    );
}

function destroy(): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${props.mailbox.name}” postafiókot és a letöltött leveleit? Ez a művelet nem vonható vissza.`,
        )
    ) {
        return;
    }

    router.delete(mailboxes.destroy(props.mailbox.id).url);
}

function destroyEmail(email: IncomingEmail): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${email.subject || "(nincs tárgy)"}” levelet? A levél csak itt törlődik, a levelezőszerveren megmarad.`,
        )
    ) {
        return;
    }

    router.delete(
        mailboxes.emails.destroy({
            mailbox: props.mailbox.id,
            incomingEmail: email.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (openEmailId.value === email.id) {
                    openEmailId.value = null;
                }
            },
        },
    );
}

function toggle(email: IncomingEmail): void {
    openEmailId.value = openEmailId.value === email.id ? null : email.id;
}

/**
 * Hand the message to the mailbox's agent: it receives it as a prompt on the mailbox's project.
 */
function sendToAgent(email: IncomingEmail): void {
    router.post(
        mailboxes.emails.read({
            mailbox: props.mailbox.id,
            incomingEmail: email.id,
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (readingEmailId.value = email.id),
            onFinish: () => (readingEmailId.value = null),
        },
    );
}

function attachmentUrl(email: IncomingEmail, attachment: Attachment): string {
    return mailboxes.emails.attachments.download({
        mailbox: props.mailbox.id,
        incomingEmail: email.id,
        attachment: attachment.id,
    }).url;
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ["KB", "MB", "GB"];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toLocaleString("hu-HU", { maximumFractionDigits: 1 })} ${units[unit]}`;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "—";
}
</script>

<template>
    <Head :title="`${mailbox.name} — Postafiókok`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading :title="mailbox.name" :description="mailbox.email" />
            <div class="flex shrink-0 flex-wrap items-center gap-1">
                <Button size="sm" :disabled="fetching" @click="fetchMail">
                    <Download class="size-4" />
                    {{ fetching ? "Letöltés…" : "Levelek letöltése" }}
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="mailboxes.edit(mailbox.id).url">
                        <Pencil class="size-4" />
                        Szerkesztés
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Törlés
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    Kapcsolat
                    <Badge
                        :variant="mailbox.is_active ? 'secondary' : 'outline'"
                        >{{ mailbox.is_active ? "Aktív" : "Inaktív" }}</Badge
                    >
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Agent
                        </dt>
                        <dd class="mt-1 break-all">
                            <Link
                                v-if="mailbox.agent"
                                :href="agents.show(mailbox.agent.id).url"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ mailbox.agent.name }}</Link
                            >
                            <span v-else class="text-muted-foreground"
                                >Nincs hozzárendelve</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Projekt
                        </dt>
                        <dd class="mt-1 break-all">
                            <Link
                                v-if="mailbox.project"
                                :href="projects.show(mailbox.project).url"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ mailbox.project.name }}</Link
                            >
                            <span v-else class="text-muted-foreground"
                                >Nincs hozzárendelve</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Olvasás
                        </dt>
                        <dd class="mt-1">
                            {{
                                mailbox.auto_read
                                    ? "Az agent automatikusan elolvassa az új leveleket"
                                    : "Kézzel, levelenként"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Válaszok
                        </dt>
                        <dd class="mt-1">
                            {{
                                mailbox.auto_reply
                                    ? "Az agent válaszai azonnal kimennek"
                                    : "Jóváhagyás után mennek ki"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Felhasználónév
                        </dt>
                        <dd class="mt-1 break-all">{{ mailbox.username }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            IMAP
                        </dt>
                        <dd class="mt-1 font-mono break-all">
                            {{ mailbox.imap_host }}:{{ mailbox.imap_port }} ({{
                                mailbox.imap_encryption
                            }}, {{ mailbox.imap_folder }})
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            SMTP
                        </dt>
                        <dd class="mt-1 font-mono break-all">
                            {{ mailbox.smtp_host }}:{{ mailbox.smtp_port }} ({{
                                mailbox.smtp_encryption
                            }})
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Utolsó letöltés
                        </dt>
                        <dd class="mt-1">
                            {{ formatDate(mailbox.last_fetched_at) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base"
                    >Bejövő levelek
                    <span class="font-normal text-muted-foreground"
                        >({{ incomingEmailsCount }})</span
                    >
                    <Badge v-if="unreadEmailsCount > 0" class="ml-1"
                        >{{ unreadEmailsCount }} olvasatlan</Badge
                    ></CardTitle
                >
                <p v-if="!canAgentRead" class="text-sm text-muted-foreground">
                    Ahhoz, hogy az agent elolvashassa a leveleket, állíts be
                    agentet és projektet a postafiók
                    <Link
                        :href="mailboxes.edit(mailbox.id).url"
                        class="font-medium underline"
                        >szerkesztésénél</Link
                    >.
                </p>
            </CardHeader>
            <CardContent>
                <div
                    v-if="incomingEmails.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    <Inbox class="mx-auto mb-2 size-6" />
                    Még nincs letöltött levél.
                </div>

                <ul v-else class="divide-y rounded-md border">
                    <li v-for="email in incomingEmails" :key="email.id">
                        <div class="flex items-center">
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-4"
                                :aria-expanded="openEmailId === email.id"
                                @click="toggle(email)"
                            >
                                <span
                                    class="flex min-w-0 items-center gap-2 sm:w-56"
                                >
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :class="
                                            email.read_at
                                                ? 'bg-transparent'
                                                : 'bg-primary'
                                        "
                                        aria-hidden="true"
                                    />
                                    <span
                                        class="truncate"
                                        :class="
                                            email.read_at
                                                ? 'text-muted-foreground'
                                                : 'font-semibold'
                                        "
                                        >{{
                                            email.from_name ||
                                            email.from_address ||
                                            "—"
                                        }}</span
                                    >
                                </span>
                                <span
                                    class="min-w-0 flex-1 truncate pl-4 sm:pl-0"
                                    :class="{ 'font-semibold': !email.read_at }"
                                    >{{ email.subject || "(nincs tárgy)" }}
                                    <span v-if="!email.read_at" class="sr-only"
                                        >(az agent még nem olvasta)</span
                                    ></span
                                >
                                <span
                                    class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <Badge
                                        v-if="hasPendingReply(email)"
                                        variant="outline"
                                        class="gap-1"
                                    >
                                        <ReplyIcon
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        Válasz vár
                                    </Badge>
                                    <span
                                        v-if="email.attachments.length > 0"
                                        class="flex items-center gap-0.5"
                                        :title="`${email.attachments.length} csatolmány`"
                                    >
                                        <Paperclip
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span class="sr-only">Csatolmány:</span>
                                        {{ email.attachments.length }}
                                    </span>
                                    {{ formatDate(email.received_at) }}
                                </span>
                            </button>
                            <Button
                                v-if="!email.read_at"
                                variant="ghost"
                                size="icon"
                                class="ml-1 size-8 shrink-0 text-muted-foreground hover:text-primary"
                                :disabled="
                                    !canAgentRead || readingEmailId === email.id
                                "
                                :aria-label="`„${email.subject || '(nincs tárgy)'}” levél elolvastatása az agenttel`"
                                :title="
                                    canAgentRead
                                        ? `Olvassa el ${mailbox.agent?.name} a(z) ${mailbox.project?.name} projekten`
                                        : 'Előbb állíts be agentet és projektet a postafióknál'
                                "
                                @click="sendToAgent(email)"
                            >
                                <BookOpenCheck class="size-3.5" />
                            </Button>
                            <span
                                v-else
                                class="ml-1 size-8 shrink-0"
                                aria-hidden="true"
                            />
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mx-1 size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                :aria-label="`„${email.subject || '(nincs tárgy)'}” levél törlése`"
                                title="Levél törlése"
                                @click="destroyEmail(email)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                        <div
                            v-if="openEmailId === email.id"
                            class="border-t bg-muted/40"
                        >
                            <p
                                v-if="email.read_at"
                                class="flex flex-wrap items-center gap-x-2 border-b px-3 py-2 text-xs text-muted-foreground"
                            >
                                <BookOpenCheck
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                Az agent elolvasta:
                                {{ formatDate(email.read_at) }}
                                <template v-if="email.prompt_run">
                                    · futás:
                                    {{
                                        promptRunStatusLabels[
                                            email.prompt_run.status
                                        ] ?? email.prompt_run.status
                                    }}
                                    <Link
                                        v-if="mailbox.project"
                                        :href="
                                            projects.prompts(mailbox.project)
                                                .url
                                        "
                                        class="font-medium underline"
                                        >Promptok megnyitása</Link
                                    >
                                </template>
                            </p>
                            <pre
                                class="max-h-96 overflow-auto px-3 py-2 font-mono text-xs whitespace-pre-wrap"
                                >{{
                                    email.body_text ||
                                    "(nincs szöveges tartalom)"
                                }}</pre>
                            <ul
                                v-if="email.attachments.length > 0"
                                class="flex flex-wrap gap-2 border-t px-3 py-2"
                                aria-label="Csatolmányok"
                            >
                                <li
                                    v-for="attachment in email.attachments"
                                    :key="attachment.id"
                                >
                                    <a
                                        :href="attachmentUrl(email, attachment)"
                                        class="flex max-w-xs items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :title="`${attachment.filename} letöltése`"
                                    >
                                        <Paperclip
                                            class="size-3.5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate">{{
                                            attachment.filename
                                        }}</span>
                                        <span
                                            class="shrink-0 text-muted-foreground"
                                            >{{
                                                formatSize(attachment.size)
                                            }}</span
                                        >
                                    </a>
                                </li>
                            </ul>
                            <section
                                v-for="reply in email.replies"
                                :key="reply.id"
                                class="border-t bg-background px-3 py-2"
                                :aria-label="`Az agent válasza: ${reply.subject}`"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <p
                                        class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                                    >
                                        <ReplyIcon
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="font-medium text-foreground"
                                            >Az agent válasza</span
                                        >
                                        → {{ reply.to.join(", ") }}
                                        <Badge
                                            :variant="
                                                reply.status === 'failed'
                                                    ? 'destructive'
                                                    : reply.status === 'sent'
                                                      ? 'secondary'
                                                      : 'outline'
                                            "
                                            >{{
                                                replyStatusLabels[reply.status]
                                            }}</Badge
                                        >
                                        <span v-if="reply.sent_at">{{
                                            formatDate(reply.sent_at)
                                        }}</span>
                                    </p>
                                    <div
                                        v-if="reply.status !== 'sent'"
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <Button
                                            size="sm"
                                            :disabled="
                                                sendingReplyId === reply.id
                                            "
                                            @click="sendReply(reply)"
                                        >
                                            <Send class="size-3.5" />
                                            {{
                                                sendingReplyId === reply.id
                                                    ? "Küldés…"
                                                    : reply.status === "failed"
                                                      ? "Újraküldés"
                                                      : "Küldés"
                                            }}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click="discardReply(reply)"
                                        >
                                            <Trash2 class="size-3.5" />
                                            Elvetés
                                        </Button>
                                    </div>
                                </div>
                                <p
                                    v-if="reply.error"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ reply.error }}
                                </p>
                                <p class="mt-1 text-xs font-medium">
                                    {{ reply.subject }}
                                </p>
                                <pre
                                    class="mt-1 max-h-96 overflow-auto font-mono text-xs whitespace-pre-wrap"
                                    >{{ reply.body_text }}</pre>
                            </section>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
