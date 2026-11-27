<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import mailboxes from "@/routes/mailboxes";
import MailboxForm from "./MailboxForm.vue";
import type { MailboxFormFields } from "./MailboxForm.vue";

defineProps<{
    encryptions: { value: string; label: string }[];
    agents: { id: number; name: string }[];
    projects: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Postafiókok", href: mailboxes.index().url },
            { title: "Új", href: mailboxes.create().url },
        ],
    },
});

const form = useForm<MailboxFormFields>({
    agent_id: "",
    project_id: "",
    name: "",
    email: "",
    from_name: "",
    username: "",
    password: "",
    imap_host: "",
    imap_port: 993,
    imap_encryption: "ssl",
    imap_folder: "INBOX",
    smtp_host: "",
    smtp_port: 465,
    smtp_encryption: "ssl",
    is_active: true,
    auto_read: false,
    auto_reply: false,
});

function submit(): void {
    form.post(mailboxes.store().url);
}
</script>

<template>
    <Head title="Új postafiók" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Új postafiók"
            description="Az IMAP (letöltés) és SMTP (küldés) hozzáférés adatai."
        />

        <MailboxForm
            :form="form"
            :encryptions="encryptions"
            :agents="agents"
            :projects="projects"
            submit-label="Postafiók létrehozása"
            :cancel-href="mailboxes.index().url"
            @submit="submit"
        />
    </div>
</template>
