import { Form, Head, Link } from '@inertiajs/react';
import {
    ShieldCheck,
    UserCheck,
    UserX,
    Users as UsersIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    ai_enabled: boolean;
    enrollments_count: number;
    active_enrollments_count: number;
    completed_units: number;
    total_units: number;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Props = {
    users: {
        data: UserRow[];
        links: PageLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search?: string; role?: string; status?: string };
    summary: {
        total: number;
        students: number;
        admins: number;
        inactive: number;
    };
};

export default function AdminUsers({ users, filters, summary }: Props) {
    return (
        <>
            <Head title="User administration" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        ADMINISTRATION
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold">
                        Users and access
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Review enrollments and progress, manage roles, and
                        deactivate access with an audit trail.
                    </p>
                </header>
                <section className="grid gap-4 md:grid-cols-4">
                    <Metric
                        icon={UsersIcon}
                        label="All users"
                        value={summary.total}
                    />
                    <Metric
                        icon={UserCheck}
                        label="Students"
                        value={summary.students}
                    />
                    <Metric
                        icon={ShieldCheck}
                        label="Administrators"
                        value={summary.admins}
                    />
                    <Metric
                        icon={UserX}
                        label="Inactive"
                        value={summary.inactive}
                    />
                </section>
                <Card>
                    <CardContent className="pt-6">
                        <Form
                            action="/admin/users"
                            method="get"
                            className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]"
                        >
                            <Input
                                name="search"
                                defaultValue={filters.search || ''}
                                placeholder="Search name or email"
                            />
                            <select
                                name="role"
                                defaultValue={filters.role || ''}
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">All roles</option>
                                <option value="student">Student</option>
                                <option value="reviewer">Reviewer</option>
                                <option value="admin">Admin</option>
                            </select>
                            <select
                                name="status"
                                defaultValue={filters.status || ''}
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Any status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <Button>Filter</Button>
                        </Form>
                    </CardContent>
                </Card>
                <div className="space-y-3">
                    {users.data.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center text-muted-foreground">
                                No users match these filters.
                            </CardContent>
                        </Card>
                    ) : (
                        users.data.map((user) => (
                            <Card key={user.id}>
                                <CardContent className="grid gap-4 pt-6 lg:grid-cols-[1.3fr_1fr_auto] lg:items-center">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-semibold">
                                                {user.name}
                                            </h2>
                                            <Badge
                                                variant={
                                                    user.is_active
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                            >
                                                {user.is_active
                                                    ? 'active'
                                                    : 'inactive'}
                                            </Badge>
                                            <Badge variant="secondary">
                                                {user.role}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {user.email} ·{' '}
                                            {user.email_verified_at
                                                ? 'verified'
                                                : 'unverified'}
                                        </p>
                                    </div>
                                    <div className="text-sm">
                                        <p>
                                            {user.active_enrollments_count}{' '}
                                            active enrollment(s)
                                        </p>
                                        <p className="text-muted-foreground">
                                            {user.completed_units} of{' '}
                                            {user.total_units} tracked units
                                            complete · AI{' '}
                                            {user.ai_enabled ? 'on' : 'off'}
                                        </p>
                                    </div>
                                    <Form
                                        action={`/admin/users/${user.id}`}
                                        method="patch"
                                        className="flex flex-wrap items-center gap-2"
                                    >
                                        <select
                                            name="role"
                                            defaultValue={user.role}
                                            className="h-9 rounded-md border bg-background px-2 text-sm"
                                        >
                                            <option value="student">
                                                Student
                                            </option>
                                            <option value="reviewer">
                                                Reviewer
                                            </option>
                                            <option value="admin">Admin</option>
                                        </select>
                                        <select
                                            name="is_active"
                                            defaultValue={
                                                user.is_active ? '1' : '0'
                                            }
                                            className="h-9 rounded-md border bg-background px-2 text-sm"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <Button size="sm">Save access</Button>
                                    </Form>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
                <nav className="flex flex-wrap gap-2">
                    {users.links.map((link, index) =>
                        link.url ? (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                asChild
                            >
                                <Link
                                    href={link.url}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ) : null,
                    )}
                </nav>
            </main>
        </>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof UsersIcon;
    label: string;
    value: number;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 pt-6">
                <Icon className="size-5 text-emerald-600" />
                <div>
                    <div className="text-2xl font-semibold">{value}</div>
                    <div className="text-sm text-muted-foreground">{label}</div>
                </div>
            </CardContent>
        </Card>
    );
}
