import { Head } from '@inertiajs/react';
import { OverviewEvents } from '@/components/operations/overview-events';
import { OverviewForecasts } from '@/components/operations/overview-forecasts';
import { OverviewMetrics } from '@/components/operations/overview-metrics';
import {
    SignalsBoard,
    SignalsHeadline,
} from '@/components/operations/overview-signals';
import OperationsLayout from '@/layouts/operations/layout';
import type {
    DailyPoint,
    ForecastSeries,
    OperationsEvent,
    Signal,
} from '@/types';

export default function Show({
    signals,
    daily,
    forecasts,
    recentEvents,
}: {
    signals: Signal[];
    daily: DailyPoint[];
    forecasts: ForecastSeries[];
    recentEvents: OperationsEvent[];
}) {
    return (
        <>
            <Head title="Overview" />

            <div className="space-y-4">
                <SignalsHeadline signals={signals} />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <SignalsBoard signals={signals} />
                    <OverviewEvents events={recentEvents} />
                </div>

                <OverviewMetrics daily={daily} />

                <OverviewForecasts forecasts={forecasts} />
            </div>
        </>
    );
}

Show.layout = OperationsLayout;
