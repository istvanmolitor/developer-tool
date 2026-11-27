<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { ArrowLeft, Pencil, Trash2 } from "@lucide/vue";
import Heading from "@/components/Heading.vue";
import MarkdownView from "@/components/MarkdownView.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import projects from "@/routes/projects";

type Project = {
    id: number;
    name: string;
    path: string;
};

type Document = {
    id: number;
    path: string;
    title: string | null;
    content: string | null;
    content_html: string;
};

const props = defineProps<{
    project: Project;
    document: Document;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Projektek", href: projects.index().url }],
    },
});

function destroy(): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${props.document.path}” dokumentumot az adatbázisból? A lemezen lévő fájl megmarad.`,
        )
    ) {
        return;
    }

    router.delete(
        projects.documents.destroy({
            project: props.project.name,
            document: props.document.id,
        }).url,
    );
}
</script>

<template>
    <Head :title="`${document.title ?? document.path} — ${project.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="document.title ?? document.path"
                :description="document.path"
            />
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="projects.documents.index(project.name).url">
                        <ArrowLeft class="size-4" />
                        Vissza
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            projects.documents.edit({
                                project: project.name,
                                document: document.id,
                            }).url
                        "
                    >
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
            <CardContent class="pt-6">
                <MarkdownView
                    v-if="document.content"
                    :html="document.content_html"
                />
                <p v-else class="text-sm text-muted-foreground">
                    Ez a dokumentum üres.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
