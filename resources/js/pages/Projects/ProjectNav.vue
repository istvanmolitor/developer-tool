<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, Terminal } from '@lucide/vue';
import { computed } from 'vue';
import projects from '@/routes/projects';

const props = defineProps<{
    name: string;
}>();

const page = usePage();

const items = computed(() => [
    {
        title: 'Áttekintés',
        icon: LayoutDashboard,
        href: projects.show(props.name).url,
        component: 'Projects/Show',
    },
    {
        title: 'Promptok',
        icon: Terminal,
        href: projects.prompts(props.name).url,
        component: 'Projects/Prompts',
    },
]);
</script>

<template>
    <nav class="flex items-center gap-1 border-b">
        <Link
            v-for="item in items"
            :key="item.component"
            :href="item.href"
            class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
            :class="
                page.component === item.component
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="item.icon" class="size-4" />
            {{ item.title }}
        </Link>
    </nav>
</template>
