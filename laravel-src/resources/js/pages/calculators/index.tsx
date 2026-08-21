import { Form, Head } from '@inertiajs/react';
import type { Calculator } from 'lucide-react';
import { ChartNoAxesCombined, Divide, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Result = {
    type: string;
    result: number;
    unit: string;
    working: string[];
} | null;

export default function Calculators({ calculation }: { calculation: Result }) {
    return (
        <>
            <Head title="Trading calculators" />
            <main className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <header className="mb-8 max-w-3xl">
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        DETERMINISTIC LEARNING TOOLS
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Calculate it. Show the working.
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        These tools use normal arithmetic—not an LLM. Check the
                        assumptions and reproduce each step in your notebook.
                    </p>
                </header>
                {calculation && (
                    <Card className="mb-6 border-emerald-300 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <CardHeader>
                            <CardTitle>
                                {calculation.result} {calculation.unit}
                            </CardTitle>
                            <CardDescription>
                                Latest calculation
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {calculation.working.map((step) => (
                                <div
                                    key={step}
                                    className="rounded-lg border bg-background/70 p-3 font-mono text-sm"
                                >
                                    {step}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                <section className="grid gap-5 lg:grid-cols-3">
                    <CalculatorCard
                        title="Position size"
                        description="Turn a defined account risk and stop distance into a lot size."
                        icon={ShieldCheck}
                        type="position_size"
                        fields={[
                            ['balance', 'Account balance', '10000'],
                            ['risk_percent', 'Risk (%)', '1'],
                            ['stop_pips', 'Stop distance (pips)', '25'],
                            [
                                'pip_value_per_lot',
                                'Pip value per standard lot',
                                '10',
                            ],
                        ]}
                    />
                    <CalculatorCard
                        title="Expectancy"
                        description="Combine win rate and average outcomes into expected R per trade."
                        icon={ChartNoAxesCombined}
                        type="expectancy"
                        fields={[
                            ['win_rate_percent', 'Win rate (%)', '45'],
                            ['average_win_r', 'Average win (R)', '2'],
                            ['average_loss_r', 'Average loss (R)', '1'],
                        ]}
                    />
                    <CalculatorCard
                        title="R multiple"
                        description="Express a trade outcome relative to the amount initially at risk."
                        icon={Divide}
                        type="r_multiple"
                        fields={[
                            ['profit_or_loss', 'Profit or loss', '150'],
                            ['initial_risk', 'Initial risk', '100'],
                        ]}
                    />
                </section>
                <div className="mt-6 rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                    <strong>Learning warning:</strong> a correct calculation
                    does not make a trade valid. Pip value can depend on pair,
                    account currency and price; verify broker contract
                    specifications before any real use.
                </div>
            </main>
        </>
    );
}

function CalculatorCard({
    title,
    description,
    icon: Icon,
    type,
    fields,
}: {
    title: string;
    description: string;
    icon: typeof Calculator;
    type: string;
    fields: string[][];
}) {
    return (
        <Card>
            <CardHeader>
                <Icon className="mb-2 size-5 text-emerald-600" />
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    action="/calculators"
                    method="post"
                    className="space-y-4"
                    disableWhileProcessing
                >
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="calculator"
                                value={type}
                            />
                            {fields.map(([name, label, value]) => (
                                <div className="grid gap-2" key={name}>
                                    <Label htmlFor={`${type}-${name}`}>
                                        {label}
                                    </Label>
                                    <Input
                                        id={`${type}-${name}`}
                                        name={name}
                                        type="number"
                                        step="any"
                                        defaultValue={value}
                                        required
                                    />
                                </div>
                            ))}
                            <Button className="w-full" type="submit">
                                {processing && <Spinner />}Calculate
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
