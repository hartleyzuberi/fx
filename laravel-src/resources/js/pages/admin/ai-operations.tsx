import { Form, Head } from '@inertiajs/react';
import { Activity, Bot, Database, ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Provider = {
    key: string;
    enabled: boolean;
    configured: boolean;
    model: string | null;
    embedding_model: string | null;
    priority: number;
    cost_class: string;
    capabilities: string[];
    privacy_policy_url: string;
    data_retention_notes: string;
    recommended_for_private_data: boolean;
    last_verified: string;
    last_success_at: string | null;
    last_failure_at: string | null;
    last_error_code: string | null;
    health: string;
    daily_budget_usd: number | null;
    monthly_budget_usd: number | null;
};
type Usage = {
    provider: string | null;
    model: string;
    task_type: string | null;
    requests: number;
    input_tokens: number;
    output_tokens: number;
    failures: number;
    fallbacks: number;
    average_latency_ms: number | null;
    estimated_cost_usd: number;
};
type Routing = {
    task_type: string;
    primary_provider: string | null;
    fallback_providers: string | null;
    local_first: boolean;
    enabled: boolean;
};
type Props = {
    runtimeMode: string;
    paidFallbackAllowed: boolean;
    providers: Provider[];
    routing: Routing[];
    usage: Usage[];
    indexState: {
        state: string;
        provider: string | null;
        model: string | null;
        record_count: number;
        last_error: string | null;
    } | null;
    flags: Array<{ key: string; enabled: boolean }>;
};

export default function AiOperations({
    runtimeMode,
    paidFallbackAllowed,
    providers,
    routing,
    usage,
    indexState,
    flags,
}: Props) {
    return (
        <>
            <Head title="AI operations" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        ADMINISTRATION
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold">
                        AI operations
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Provider-neutral routing, health, privacy and spend
                        controls. Secrets remain environment-only.
                    </p>
                </header>
                <section className="grid gap-4 md:grid-cols-3">
                    <Summary
                        icon={Bot}
                        label="Runtime mode"
                        value={runtimeMode}
                    />
                    <Summary
                        icon={ShieldCheck}
                        label="Paid fallback"
                        value={
                            paidFallbackAllowed
                                ? 'explicitly allowed'
                                : 'prohibited'
                        }
                    />
                    <Summary
                        icon={Database}
                        label="Course index"
                        value={indexState?.state || 'not built'}
                    />
                </section>
                {!paidFallbackAllowed && (
                    <div className="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100">
                        Cost guard is active: providers classified as paid
                        cannot be selected.
                    </div>
                )}
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">Providers</h2>
                    {providers.map((provider) => (
                        <Card key={provider.key}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <CardTitle className="capitalize">
                                        {provider.key}
                                    </CardTitle>
                                    <div className="flex gap-2">
                                        <Badge variant="outline">
                                            {provider.health}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {provider.cost_class}
                                        </Badge>
                                        <Badge
                                            variant={
                                                provider.configured
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {provider.configured
                                                ? 'configured'
                                                : 'not configured'}
                                        </Badge>
                                    </div>
                                </div>
                                <CardDescription>
                                    {provider.data_retention_notes}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex flex-wrap gap-2">
                                    {provider.capabilities.map((capability) => (
                                        <Badge
                                            key={capability}
                                            variant="outline"
                                        >
                                            {capability}
                                        </Badge>
                                    ))}
                                </div>
                                <Form
                                    action={`/admin/ai/providers/${provider.key}`}
                                    method="patch"
                                    className="grid gap-3 md:grid-cols-4"
                                >
                                    <label className="text-sm">
                                        Enabled
                                        <select
                                            name="enabled"
                                            defaultValue={
                                                provider.enabled ? '1' : '0'
                                            }
                                            className="mt-1 h-9 w-full rounded-md border bg-background px-2"
                                        >
                                            <option value="1">Yes</option>
                                            <option value="0">No</option>
                                        </select>
                                    </label>
                                    <label className="text-sm">
                                        Model
                                        <Input
                                            name="model"
                                            defaultValue={provider.model || ''}
                                            placeholder="Required; no hard-coded default"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Embedding model
                                        <Input
                                            name="embedding_model"
                                            defaultValue={
                                                provider.embedding_model || ''
                                            }
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Priority
                                        <Input
                                            name="priority"
                                            type="number"
                                            min="1"
                                            max="1000"
                                            defaultValue={provider.priority}
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Cost class
                                        <select
                                            name="cost_class"
                                            defaultValue={provider.cost_class}
                                            className="mt-1 h-9 w-full rounded-md border bg-background px-2"
                                        >
                                            <option value="local">Local</option>
                                            <option value="free">
                                                Free/free-tier
                                            </option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </label>
                                    <label className="text-sm">
                                        Daily budget USD
                                        <Input
                                            name="daily_budget_usd"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={
                                                provider.daily_budget_usd || ''
                                            }
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Monthly budget USD
                                        <Input
                                            name="monthly_budget_usd"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={
                                                provider.monthly_budget_usd ||
                                                ''
                                            }
                                        />
                                    </label>
                                    <div className="flex items-end gap-2">
                                        <Button>Save</Button>
                                    </div>
                                </Form>
                                <Form
                                    action={`/admin/ai/providers/${provider.key}/test`}
                                    method="post"
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={!provider.configured}
                                    >
                                        Test minimal inference
                                    </Button>
                                </Form>
                                <p className="text-xs text-muted-foreground">
                                    Metadata verified {provider.last_verified}.
                                    Last success:{' '}
                                    {provider.last_success_at || 'never'}; last
                                    error: {provider.last_error_code || 'none'}.{' '}
                                    <a
                                        href={provider.privacy_policy_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="underline"
                                    >
                                        Privacy policy
                                    </a>
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <section className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Capability flags</CardTitle>
                            <CardDescription>
                                Major AI features can be stopped without a
                                deployment.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {flags.map((flag) => (
                                <Form
                                    key={flag.key}
                                    action={`/admin/feature-flags/${flag.key}`}
                                    method="patch"
                                    className="flex items-center justify-between gap-4 rounded-md border p-3"
                                >
                                    <span className="text-sm font-medium">
                                        {flag.key}
                                    </span>
                                    <div className="flex gap-2">
                                        <select
                                            name="enabled"
                                            defaultValue={
                                                flag.enabled ? '1' : '0'
                                            }
                                            className="h-9 rounded-md border bg-background px-2 text-sm"
                                        >
                                            <option value="1">On</option>
                                            <option value="0">Off</option>
                                        </select>
                                        <Button size="sm">Apply</Button>
                                    </div>
                                </Form>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Routing rules</CardTitle>
                            <CardDescription>
                                Per-task routing avoids locking an entire
                                session to one provider.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {routing.map((rule) => {
                                const fallbacks = parseFallbacks(
                                    rule.fallback_providers,
                                );

                                return (
                                    <Form
                                        key={rule.task_type}
                                        action={`/admin/ai/routing/${rule.task_type}`}
                                        method="patch"
                                        className="space-y-3 rounded-md border p-3"
                                    >
                                        <div className="font-medium capitalize">
                                            {rule.task_type.replace('_', ' ')}
                                        </div>
                                        <div className="grid gap-2 md:grid-cols-2">
                                            <select
                                                name="primary_provider"
                                                defaultValue={
                                                    rule.primary_provider || ''
                                                }
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                            >
                                                <option value="">
                                                    Default primary
                                                </option>
                                                {providers.map((provider) => (
                                                    <option
                                                        key={provider.key}
                                                        value={provider.key}
                                                    >
                                                        {provider.key}
                                                    </option>
                                                ))}
                                            </select>
                                            {[0, 1].map((position) => (
                                                <select
                                                    key={position}
                                                    name="fallback_providers[]"
                                                    defaultValue={
                                                        fallbacks[position] ||
                                                        ''
                                                    }
                                                    className="h-9 rounded-md border bg-background px-2 text-sm"
                                                >
                                                    <option value="">
                                                        No fallback
                                                    </option>
                                                    {providers.map(
                                                        (provider) => (
                                                            <option
                                                                key={
                                                                    provider.key
                                                                }
                                                                value={
                                                                    provider.key
                                                                }
                                                            >
                                                                {provider.key}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ))}
                                            <select
                                                name="local_first"
                                                defaultValue={
                                                    rule.local_first ? '1' : '0'
                                                }
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                            >
                                                <option value="0">
                                                    Configured priority
                                                </option>
                                                <option value="1">
                                                    Local first
                                                </option>
                                            </select>
                                            <select
                                                name="enabled"
                                                defaultValue={
                                                    rule.enabled ? '1' : '0'
                                                }
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                            >
                                                <option value="1">
                                                    Enabled
                                                </option>
                                                <option value="0">
                                                    Disabled
                                                </option>
                                            </select>
                                        </div>
                                        <Button size="sm">Save route</Button>
                                    </Form>
                                );
                            })}
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="size-5" /> Usage and fallback
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {usage.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No provider requests have been recorded.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="p-2">
                                                Provider/model
                                            </th>
                                            <th className="p-2">Task</th>
                                            <th className="p-2">Requests</th>
                                            <th className="p-2">Failures</th>
                                            <th className="p-2">Fallbacks</th>
                                            <th className="p-2">Latency</th>
                                            <th className="p-2">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {usage.map((row, i) => (
                                            <tr key={i} className="border-b">
                                                <td className="p-2">
                                                    {row.provider || 'legacy'} /{' '}
                                                    {row.model}
                                                </td>
                                                <td className="p-2">
                                                    {row.task_type || 'legacy'}
                                                </td>
                                                <td className="p-2">
                                                    {row.requests}
                                                </td>
                                                <td className="p-2">
                                                    {row.failures}
                                                </td>
                                                <td className="p-2">
                                                    {row.fallbacks}
                                                </td>
                                                <td className="p-2">
                                                    {Math.round(
                                                        row.average_latency_ms ||
                                                            0,
                                                    )}{' '}
                                                    ms
                                                </td>
                                                <td className="p-2">
                                                    $
                                                    {Number(
                                                        row.estimated_cost_usd ||
                                                            0,
                                                    ).toFixed(4)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function Summary({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Bot;
    label: string;
    value: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 pt-6">
                <Icon className="size-5 text-emerald-600" />
                <div>
                    <div className="font-semibold">{value}</div>
                    <div className="text-sm text-muted-foreground">{label}</div>
                </div>
            </CardContent>
        </Card>
    );
}

function parseFallbacks(value: string | null): string[] {
    try {
        const parsed: unknown = JSON.parse(value || '[]');

        return Array.isArray(parsed)
            ? parsed.filter((item): item is string => typeof item === 'string')
            : [];
    } catch {
        return [];
    }
}
