<?php

namespace Homemove\AbTesting\Contracts;

/**
 * Host-app hook for step-level funnel data. The package's own ab_events table
 * is an upserted per-(user,event) counter with no inter-event ordering, so it
 * can only ever produce a coarse reach funnel. Apps that keep a real telemetry
 * stream (page views, form steps) can bind an implementation of this interface
 * and the dashboard will render their step-level funnel instead.
 *
 * Bind in the HOST app's service provider:
 *   $this->app->bind(FunnelStepDataProvider::class, MyFunnelProvider::class);
 * The package deliberately ships no default binding.
 */
interface FunnelStepDataProvider
{
    /**
     * Step-level funnel for one experiment, or null when this provider has no
     * data for it (the dashboard then falls back to the ab_events reach funnel).
     *
     * @return null|array{
     *     source: string,
     *     steps: list<array{
     *         key: string,
     *         label: string,
     *         index: int,
     *         counts: array<string, int>
     *     }>
     * }  steps ordered by index ascending; counts = variant => distinct users reaching the step
     */
    public function funnelFor(string $experimentName, ?string $deviceType = null): ?array;
}
