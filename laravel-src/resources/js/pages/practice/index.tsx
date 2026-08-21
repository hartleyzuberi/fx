import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, CircleDashed, LockKeyhole } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Milestone = {
    chapter: number;
    title: string;
    description: string;
    status: string;
};
type Strategy = {
    id: string;
    name: string;
    version: number;
    status: string;
    frozenAt: string | null;
};
type Backtest = {
    id: string;
    strategyId: string;
    datasetRole: string;
    instrument: string;
    timeframe: string;
    status: string;
    metrics: Record<string, number>;
    observations: number;
};
type Program = {
    id: string;
    strategyId: string;
    status: string;
    startingBalance: string;
    riskPercent: string;
    trades: number;
    adherence: number;
};

export default function PracticeHub({
    milestones,
    strategies,
    backtests,
    programs,
    robustnessRuns,
}: {
    milestones: Milestone[];
    strategies: Strategy[];
    backtests: Backtest[];
    programs: Program[];
    robustnessRuns: number;
}) {
    return (
        <>
            <Head title="Practice lab" />
            <main className="mx-auto w-full max-w-5xl p-4 md:p-8">
                <header className="mb-8 max-w-3xl">
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        FROM KNOWING TO EVIDENCE
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Practice lab
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Platform drills, charts, macro studies, strategy testing
                        and serious demo are different stages. Each opens only
                        when its curriculum prerequisites are satisfied.
                    </p>
                </header>
                <div className="relative space-y-4 before:absolute before:top-8 before:bottom-8 before:left-6 before:w-px before:bg-border">
                    {milestones.map((milestone) => {
                        const locked = milestone.status === 'locked';
                        const passed = ['passed', 'mastered'].includes(
                            milestone.status,
                        );

                        return (
                            <article
                                key={milestone.chapter}
                                className={`relative ml-12 rounded-xl border p-5 ${locked ? 'bg-muted/30 text-muted-foreground' : 'bg-card'}`}
                            >
                                <div className="absolute top-5 -left-[2.55rem] flex size-8 items-center justify-center rounded-full border bg-background">
                                    {locked ? (
                                        <LockKeyhole className="size-4" />
                                    ) : passed ? (
                                        <CheckCircle2 className="size-4 text-emerald-600" />
                                    ) : (
                                        <CircleDashed className="size-4 text-emerald-600" />
                                    )}
                                </div>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="text-xs font-medium">
                                            CHAPTER {milestone.chapter}{' '}
                                            MILESTONE
                                        </div>
                                        <h2 className="mt-1 text-lg font-semibold text-foreground">
                                            {milestone.title}
                                        </h2>
                                        <p className="mt-1 text-sm">
                                            {milestone.description}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {milestone.status.replace('_', ' ')}
                                    </Badge>
                                </div>
                                {locked && (
                                    <p className="mt-3 text-xs">
                                        Complete and master the linked
                                        curriculum stage to open this evidence
                                        system.
                                    </p>
                                )}
                            </article>
                        );
                    })}
                </div>
                <EvidenceSystems
                    milestones={milestones}
                    strategies={strategies}
                    backtests={backtests}
                    programs={programs}
                    robustnessRuns={robustnessRuns}
                />
                <div className="mt-8 rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                    <strong>Serious demo is not platform practice.</strong> It
                    requires a frozen strategy, development and out-of-sample
                    observations, robustness evidence and the strategy gate.
                    Micro-live readiness later requires at least 100 legitimate
                    demo trades and at least 95% rule adherence.
                </div>
            </main>
        </>
    );
}

const strategyFields = [
    'market_premise',
    'instruments',
    'timeframes',
    'trading_hours',
    'setup_definition',
    'market_context',
    'entry_trigger',
    'stop_logic',
    'exit_logic',
    'risk_rules',
    'news_rules',
    'filters',
    'invalidation',
    'forbidden_conditions',
    'minimum_sample_size',
    'retirement_rules',
];

function EvidenceSystems({
    milestones,
    strategies,
    backtests,
    programs,
    robustnessRuns,
}: {
    milestones: Milestone[];
    strategies: Strategy[];
    backtests: Backtest[];
    programs: Program[];
    robustnessRuns: number;
}) {
    const available = (chapter: number) =>
        milestones.find((item) => item.chapter === chapter)?.status !==
        'locked';
    const frozen = strategies.filter((item) => item.status === 'frozen');
    const activeRuns = backtests.filter(
        (item) => item.status === 'in_progress',
    );
    const activePrograms = programs.filter((item) => item.status === 'active');

    return (
        <section className="mt-10 space-y-4">
            <div>
                <h2 className="text-2xl font-semibold">Evidence systems</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Each tool stores real, user-owned records. The server
                    enforces chapter access and evidence predicates even if a
                    URL is called directly.
                </p>
            </div>

            <ToolPanel
                title="Strategy builder"
                locked={!available(51)}
                lockText="Chapter 51 must be available."
            >
                <Form action="/strategies" method="post" className="space-y-4">
                    <input
                        name="name"
                        required
                        maxLength={120}
                        className="w-full rounded-md border bg-background p-2.5"
                        placeholder="Strategy name"
                    />
                    <div className="grid gap-3 md:grid-cols-2">
                        {strategyFields.map((field) => (
                            <label key={field} className="space-y-1 text-xs">
                                <span className="font-medium capitalize">
                                    {field.replaceAll('_', ' ')}
                                </span>
                                <textarea
                                    name={`specification[${field}]`}
                                    required
                                    rows={2}
                                    className="w-full rounded-md border bg-background p-2.5 text-sm"
                                />
                            </label>
                        ))}
                    </div>
                    <Button type="submit">Create immutable version</Button>
                </Form>
                <RecordList>
                    {strategies.map((strategy) => (
                        <div
                            key={strategy.id}
                            className="flex items-center gap-3 rounded-lg border p-3 text-sm"
                        >
                            <span className="font-medium">
                                {strategy.name} v{strategy.version}
                            </span>
                            <Badge variant="outline">{strategy.status}</Badge>
                            {strategy.status === 'draft' && (
                                <Form
                                    action={`/strategies/${strategy.id}/freeze`}
                                    method="post"
                                    className="ml-auto"
                                >
                                    <Button size="sm" variant="outline">
                                        Freeze version
                                    </Button>
                                </Form>
                            )}
                        </div>
                    ))}
                </RecordList>
            </ToolPanel>

            <ToolPanel
                title="Backtest and replay log"
                locked={!available(52)}
                lockText="Chapter 52 and a frozen strategy are required."
            >
                <Form
                    action="/backtests"
                    method="post"
                    className="grid gap-3 md:grid-cols-3"
                >
                    <SelectStrategy strategies={frozen} />
                    <select
                        name="dataset_role"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                    >
                        <option value="development">Development</option>
                        <option value="out_of_sample">Out of sample</option>
                        <option value="walk_forward">Walk forward</option>
                    </select>
                    <input
                        name="instrument"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="EURUSD"
                    />
                    <input
                        name="timeframe"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="H4"
                    />
                    <input
                        name="period_start"
                        type="date"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                    />
                    <input
                        name="period_end"
                        type="date"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                    />
                    <input
                        name="cost_assumptions[spread_pips]"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="Spread pips"
                    />
                    <input
                        name="cost_assumptions[slippage_pips]"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="Slippage pips"
                    />
                    <Button type="submit" disabled={frozen.length === 0}>
                        Create run
                    </Button>
                </Form>
                {activeRuns.map((run) => (
                    <div key={run.id} className="mt-4 rounded-lg border p-4">
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <strong>
                                {run.instrument} · {run.timeframe}
                            </strong>
                            <Badge variant="outline">{run.datasetRole}</Badge>
                            <span className="text-muted-foreground">
                                {run.observations} observations
                            </span>
                        </div>
                        <Form
                            action={`/backtests/${run.id}/observations`}
                            method="post"
                            className="mt-3 grid gap-2 md:grid-cols-4"
                        >
                            <input
                                name="observed_at"
                                type="datetime-local"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                            />
                            <input
                                name="r_result"
                                type="number"
                                step="0.01"
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="R result"
                            />
                            <input
                                name="cost_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Cost"
                            />
                            <input
                                name="setup_snapshot[signal]"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Setup snapshot"
                            />
                            <Button type="submit" size="sm">
                                Append observation
                            </Button>
                        </Form>
                        <Form
                            action={`/backtests/${run.id}/complete`}
                            method="post"
                            className="mt-3"
                        >
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={run.observations === 0}
                            >
                                Freeze and calculate metrics
                            </Button>
                        </Form>
                    </div>
                ))}
            </ToolPanel>

            <ToolPanel
                title={`Robustness lab · ${robustnessRuns} recorded`}
                locked={!available(56)}
                lockText="Chapter 56 and a frozen strategy are required."
            >
                <Form
                    action="/robustness-runs"
                    method="post"
                    className="grid gap-3 md:grid-cols-3"
                >
                    <SelectStrategy strategies={frozen} />
                    <select
                        name="test_type"
                        className="rounded-md border bg-background p-2.5 text-sm"
                    >
                        <option value="cost_stress">Cost stress</option>
                        <option value="parameter_sensitivity">
                            Parameter sensitivity
                        </option>
                        <option value="concentration">Concentration</option>
                        <option value="alternate_period">
                            Alternate period
                        </option>
                    </select>
                    <select
                        name="conclusion"
                        className="rounded-md border bg-background p-2.5 text-sm"
                    >
                        <option value="survives">Survives</option>
                        <option value="fragile">Fragile</option>
                        <option value="fails">Fails</option>
                    </select>
                    <input
                        name="parameters[description]"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="What changed?"
                    />
                    <input
                        name="results[summary]"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="Observed result"
                    />
                    <Button type="submit" disabled={frozen.length === 0}>
                        Save robustness evidence
                    </Button>
                </Form>
            </ToolPanel>

            <ToolPanel
                title="Serious demo and trade journal"
                locked={!available(66)}
                lockText="Chapter 66 plus strategy, development, out-of-sample, and robustness evidence are required."
            >
                <Form
                    action="/demo-programs"
                    method="post"
                    className="grid gap-3 md:grid-cols-4"
                >
                    <SelectStrategy strategies={frozen} />
                    <input
                        name="starting_balance"
                        type="number"
                        min="1"
                        step="0.01"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="Starting balance"
                    />
                    <input
                        name="risk_percent"
                        type="number"
                        min="0.01"
                        max="2"
                        step="0.01"
                        required
                        className="rounded-md border bg-background p-2.5 text-sm"
                        placeholder="Risk %"
                    />
                    <Button type="submit" disabled={frozen.length === 0}>
                        Start serious demo
                    </Button>
                </Form>
                {activePrograms.map((program) => (
                    <div
                        key={program.id}
                        className="mt-4 rounded-lg border p-4"
                    >
                        <div className="flex flex-wrap gap-2 text-sm">
                            <Badge>{program.trades} / 100 trades</Badge>
                            <Badge variant="outline">
                                {program.adherence.toFixed(1)}% adherence
                            </Badge>
                        </div>
                        <Form
                            action={`/demo-programs/${program.id}/trades`}
                            method="post"
                            className="mt-4 grid gap-2 md:grid-cols-4"
                        >
                            <input
                                name="instrument"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="EURUSD"
                            />
                            <select
                                name="direction"
                                className="rounded-md border bg-background p-2 text-sm"
                            >
                                <option value="long">Long</option>
                                <option value="short">Short</option>
                            </select>
                            <input
                                name="opened_at"
                                type="datetime-local"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                            />
                            <input
                                name="closed_at"
                                type="datetime-local"
                                className="rounded-md border bg-background p-2 text-sm"
                            />
                            <input
                                name="entry_price"
                                type="number"
                                min="0"
                                step="0.00001"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Entry"
                            />
                            <input
                                name="stop_price"
                                type="number"
                                min="0"
                                step="0.00001"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Stop"
                            />
                            <input
                                name="exit_price"
                                type="number"
                                min="0"
                                step="0.00001"
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Exit"
                            />
                            <input
                                name="planned_risk"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Cash risk"
                            />
                            <input
                                name="r_result"
                                type="number"
                                step="0.01"
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="R result"
                            />
                            <select
                                name="rules_followed"
                                className="rounded-md border bg-background p-2 text-sm"
                            >
                                <option value="1">Rules followed</option>
                                <option value="0">Rule violation</option>
                            </select>
                            <input
                                type="hidden"
                                name="checklist[pre_trade]"
                                value="1"
                            />
                            <input
                                name="reflection"
                                className="rounded-md border bg-background p-2 text-sm"
                                placeholder="Reflection"
                            />
                            <Button type="submit">Append trade</Button>
                        </Form>
                    </div>
                ))}
            </ToolPanel>
        </section>
    );
}

function ToolPanel({
    title,
    locked,
    lockText,
    children,
}: {
    title: string;
    locked: boolean;
    lockText: string;
    children: React.ReactNode;
}) {
    return (
        <details className="rounded-xl border bg-card p-5" open={!locked}>
            <summary className="cursor-pointer font-semibold">
                {title}{' '}
                {locked && (
                    <Badge variant="outline" className="ml-2">
                        Locked
                    </Badge>
                )}
            </summary>
            {locked ? (
                <p className="mt-3 text-sm text-muted-foreground">{lockText}</p>
            ) : (
                <div className="mt-5">{children}</div>
            )}
        </details>
    );
}

function SelectStrategy({ strategies }: { strategies: Strategy[] }) {
    return (
        <select
            name="strategy_version_id"
            required
            className="rounded-md border bg-background p-2.5 text-sm"
        >
            <option value="">Select frozen strategy</option>
            {strategies.map((strategy) => (
                <option key={strategy.id} value={strategy.id}>
                    {strategy.name} v{strategy.version}
                </option>
            ))}
        </select>
    );
}

function RecordList({ children }: { children: React.ReactNode }) {
    return <div className="mt-5 space-y-2">{children}</div>;
}
