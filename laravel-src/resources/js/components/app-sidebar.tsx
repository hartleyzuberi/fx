import { Link, usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    Calculator,
    Dumbbell,
    LayoutGrid,
    Map,
    NotebookPen,
    Library,
    ShieldCheck,
    Bot,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Course map', href: '/course', icon: Map },
    { title: 'Notebooks', href: '/notebooks', icon: NotebookPen },
    { title: 'Calculators', href: '/calculators', icon: Calculator },
    { title: 'Practice lab', href: '/practice', icon: Dumbbell },
    { title: 'Resources', href: '/resources', icon: Library },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Learning methodology',
        href: '/course',
        icon: BookOpenCheck,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const visibleItems =
        auth.user.role === 'admin'
            ? [
                  ...mainNavItems,
                  {
                      title: 'Users',
                      href: '/admin/users',
                      icon: Users,
                  },
                  {
                      title: 'AI operations',
                      href: '/admin/ai',
                      icon: Bot,
                  },
                  {
                      title: 'Content audit',
                      href: '/admin/content-audit',
                      icon: ShieldCheck,
                  },
              ]
            : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
