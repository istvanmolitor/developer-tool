<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Bot,
    FolderKanban,
    LayoutGrid,
    Mail,
    Settings,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import agents from '@/routes/agents';
import { edit as appSettings } from '@/routes/app-settings';
import { dashboard } from '@/routes';
import mailboxes from '@/routes/mailboxes';
import projects from '@/routes/projects';
import documentations from '@/routes/projects/documentations';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Vezérlőpult',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Projektek',
        href: projects.index(),
        icon: FolderKanban,
    },
    {
        title: 'Agentek',
        href: agents.index(),
        icon: Bot,
    },
    {
        title: 'Postafiókok',
        href: mailboxes.index(),
        icon: Mail,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Beállítások',
        href: appSettings(),
        icon: Settings,
    },
    {
        title: 'Dokumentáció',
        href: documentations.index('developer-tool'),
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
