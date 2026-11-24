<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import MarkdownView from '@/components/MarkdownView.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import projects from '@/routes/projects';

type Project = {
    id: number;
    name: string;
    path: string;
};

type Documentation = {
    id: number;
    path: string;
    title: string | null;
    content: string | null;
    content_html: string;
};

const props = defineProps<{
    project: Project;
    documentation: Documentation;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projektek', href: projects.index().url }],
    },
});

function destroy(): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${props.documentation.path}” dokumentációt az adatbázisból? A lemezen lévő fájl megmarad.`,
        )
    ) {
        return;
    }

    router.delete(
        projects.documentations.destroy({
            project: props.project.name,
            documentation: props.documentation.id,
        }).url,
    );
}
</script>

<template>
    <Head
        :title="`${documentation.title ?? documentation.path} — ${project.name}`"
    />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="documentation.title ?? documentation.path"
                :description="documentation.path"
            />
            <div class="flex shrink-0 items-center gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="projects.documentations.index(project.name).url"
                    >
                        <ArrowLeft class="size-4" />
                        Vissza
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            projects.documentations.edit({
                                project: project.name,
                                documentation: documentation.id,
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
                    v-if="documentation.content"
                    :html="documentation.content_html"
                />
                <p v-else class="text-sm text-muted-foreground">
                    Ez a dokumentáció üres.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
