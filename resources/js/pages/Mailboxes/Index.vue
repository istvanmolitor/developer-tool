<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { Bot, ChevronRight, Mail, Pencil, Plus, Trash2 } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import mailboxRoutes from "@/routes/mailboxes";

type Mailbox = {
    id: number;
    name: string;
    email: string;
    imap_host: string;
    smtp_host: string;
    is_active: boolean;
    last_fetched_at: string | null;
    incoming_emails_count: number;
    unread_emails_count: number;
    agent: { id: number; name: string } | null;
    project: { id: number; name: string } | null;
};

defineProps<{
    mailboxes: Mailbox[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Postafiókok", href: mailboxRoutes.index().url },
        ],
    },
});

function destroy(mailbox: Mailbox): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${mailbox.name}” postafiókot és a letöltött leveleit? Ez a művelet nem vonható vissza.`,
        )
    ) {
        return;
    }

    router.delete(mailboxRoutes.destroy(mailbox.id).url, {
        preserveScroll: true,
    });
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString("hu-HU") : "még soha";
}
</script>

<template>
    <Head title="Postafiókok" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Postafiókok"
                description="Levelek letöltése (IMAP) és küldése (SMTP)."
            />
            <Button as-child>
                <Link :href="mailboxRoutes.create().url">
                    <Plus class="size-4" />
                    Új postafiók
                </Link>
            </Button>
        </div>

        <div
            v-if="mailboxes.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <Mail class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p class="font-medium">Még nincsenek postafiókok</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Hozz létre egyet az
                <Link
                    :href="mailboxRoutes.create().url"
                    class="font-medium underline"
                    >Új postafiók</Link
                >
                gombbal.
            </p>
        </div>

        <div
            v-else
            class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card
                v-for="mailbox in mailboxes"
                :key="mailbox.id"
                class="flex h-full flex-col transition-colors hover:border-primary/50"
            >
                <CardHeader>
                    <CardTitle class="min-w-0">
                        <Link
                            :href="mailboxRoutes.show(mailbox.id).url"
                            class="flex items-center gap-2 break-all outline-none hover:underline focus-visible:underline"
                        >
                            <Mail
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            {{ mailbox.name }}
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                        </Link>
                    </CardTitle>
                    <p class="text-sm break-all text-muted-foreground">
                        {{ mailbox.email }}
                    </p>
                    <p
                        v-if="mailbox.agent"
                        class="flex items-center gap-1.5 text-sm text-muted-foreground"
                    >
                        <Bot class="size-3.5 shrink-0" aria-hidden="true" />
                        <span class="sr-only">Agent:</span>
                        {{ mailbox.agent.name }}
                        <template v-if="mailbox.project">
                            <span aria-hidden="true">·</span>
                            <span class="sr-only">projekt:</span>
                            {{ mailbox.project.name }}
                        </template>
                    </p>
                </CardHeader>
                <CardContent
                    class="mt-auto flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <Badge
                        :variant="mailbox.is_active ? 'secondary' : 'outline'"
                        >{{ mailbox.is_active ? "Aktív" : "Inaktív" }}</Badge
                    >
                    <Badge variant="outline"
                        >{{ mailbox.incoming_emails_count }} levél</Badge
                    >
                    <Badge v-if="mailbox.unread_emails_count > 0"
                        >{{ mailbox.unread_emails_count }} olvasatlan</Badge
                    >
                    <span
                        >Utolsó letöltés:
                        {{ formatDate(mailbox.last_fetched_at) }}</span
                    >
                    <div
                        class="flex w-full items-center justify-end gap-1 pt-1"
                    >
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="mailboxRoutes.edit(mailbox.id).url">
                                <Pencil class="size-3.5" />
                                Szerkesztés
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(mailbox)"
                        >
                            <Trash2 class="size-3.5" />
                            Törlés
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
