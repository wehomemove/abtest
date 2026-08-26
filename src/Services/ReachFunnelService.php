<?php

namespace Homemove\AbTesting\Services;

use Homemove\AbTesting\Models\Event;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Models\UserAssignment;

/**
 * The package-native funnel: distinct users REACHING each named event, per
 * variant. ab_events carries no inter-event ordering (one upserted row per
 * user+event; created_at = first occurrence), so this is honestly a
 * first-touch reach funnel, not a sequenced one — the dashboard labels it as
 * such. Step order comes from the experiment's custom_events list when the
 * team has configured one, else from the median first-touch time of each
 * observed event, which approximates flow order well enough to start from.
 */
class ReachFunnelService
{
    /**
     * @return array{source: string, steps: list<array{key: string, label: string, index: int, counts: array<string, int>}>, biggest_drop_after: array<string, string|null>}|null
     */
    public function funnelFor(Experiment $experiment, ?string $deviceType = null): ?array
    {
        $variants = array_keys($experiment->variants ?? []);
        if ($variants === []) {
            return null;
        }

        // variant => assigned counts form the synthetic first step.
        $assigned = UserAssignment::where('experiment_id', $experiment->id)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->selectRaw('variant, COUNT(*) as total')
            ->groupBy('variant')
            ->pluck('total', 'variant')
            ->all();

        // event_name => variant => distinct users (single grouped query).
        $reach = [];
        Event::where('experiment_id', $experiment->id)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->selectRaw('event_name, variant, COUNT(DISTINCT user_id) as users')
            ->groupBy('event_name', 'variant')
            ->get()
            ->each(function ($row) use (&$reach) {
                $reach[$row->event_name][$row->variant] = (int) $row->users;
            });

        $eventOrder = $this->stepOrder($experiment, array_keys($reach));

        $steps = [[
            'key' => '_assigned',
            'label' => 'Assigned',
            'index' => 0,
            'counts' => array_map(fn ($v) => (int) ($assigned[$v] ?? 0), array_combine($variants, $variants)),
        ]];
        foreach ($eventOrder as $i => $eventName) {
            $steps[] = [
                'key' => $eventName,
                'label' => ucfirst(str_replace(['_', '-'], ' ', $eventName)),
                'index' => $i + 1,
                'counts' => array_map(fn ($v) => (int) ($reach[$eventName][$v] ?? 0), array_combine($variants, $variants)),
            ];
        }

        if (count($steps) < 2) {
            return null;
        }

        return [
            'source' => 'ab_events',
            'steps' => $steps,
            'biggest_drop_after' => $this->biggestDropAfter($steps, $variants),
        ];
    }

    /**
     * Ordered step list: the experiment's configured custom_events (kept only
     * where events actually exist), else observed events by first first-touch,
     * with 'conversion' pinned last either way.
     *
     * @param  list<string>  $observed
     * @return list<string>
     */
    protected function stepOrder(Experiment $experiment, array $observed): array
    {
        $configured = array_values(array_filter(
            (array) ($experiment->custom_events ?? []),
            fn ($e) => is_string($e) && in_array($e, $observed, true)
        ));

        if ($configured !== []) {
            $order = $configured;
        } else {
            $firstTouch = Event::where('experiment_id', $experiment->id)
                ->selectRaw('event_name, MIN(created_at) as first_seen')
                ->groupBy('event_name')
                ->orderBy('first_seen')
                ->pluck('event_name')
                ->all();
            $order = array_values(array_intersect($firstTouch, $observed));
        }

        $order = array_values(array_filter($order, fn ($e) => $e !== 'conversion'));
        if (in_array('conversion', $observed, true)) {
            $order[] = 'conversion';
        }

        return $order;
    }

    /**
     * Public entry for provider-supplied steps that arrive without a
     * biggest-drop computation (same shape as the internal one).
     *
     * @param  list<array{key: string, counts: array<string, int>}>  $steps
     * @param  list<string>  $variants
     * @return array<string, string|null>
     */
    public function funnelBiggestDrop(array $steps, array $variants): array
    {
        return $this->biggestDropAfter($steps, $variants);
    }

    /**
     * Per variant, the step key AFTER which the largest share of that arm's
     * users is lost (relative retention step-over-step). Null when nothing drops.
     *
     * @param  list<array{key: string, counts: array<string, int>}>  $steps
     * @param  list<string>  $variants
     * @return array<string, string|null>
     */
    protected function biggestDropAfter(array $steps, array $variants): array
    {
        $result = [];
        foreach ($variants as $variant) {
            $worstKey = null;
            $worstRetention = 1.0;
            for ($i = 1, $n = count($steps); $i < $n; $i++) {
                $prev = $steps[$i - 1]['counts'][$variant] ?? 0;
                $curr = $steps[$i]['counts'][$variant] ?? 0;
                if ($prev <= 0) {
                    continue;
                }
                $retention = $curr / $prev;
                if ($retention < $worstRetention) {
                    $worstRetention = $retention;
                    $worstKey = $steps[$i - 1]['key'];
                }
            }
            $result[$variant] = $worstKey;
        }

        return $result;
    }
}
