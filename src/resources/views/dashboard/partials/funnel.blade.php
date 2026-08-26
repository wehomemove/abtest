{{-- Funnel: steps x arms, server-rendered. Bar width = % of that arm's
     first-step count; retention % shown per transition; the transition losing
     the largest share of an arm's users is highlighted. Data comes from a
     host-bound FunnelStepDataProvider (step-level) or the package's own
     ab_events reach funnel (first-touch, labelled honestly). --}}
@php
    $funnel = $stats['funnel'] ?? null;
@endphp
@if($funnel && count($funnel['steps'] ?? []) >= 2)
    @php
        $funnelVariants = array_keys($experiment->variants ?? []);
        $steps = $funnel['steps'];
        $dropAfter = $funnel['biggest_drop_after'] ?? [];
        $variantPalette = ['control' => '#6B7280'];
        $accentPool = ['#DC2626', '#10B981', '#F59E0B', '#3B82F6', '#8B5CF6'];
        $accentIndex = 0;
        foreach ($funnelVariants as $fv) {
            if (!isset($variantPalette[$fv])) {
                $variantPalette[$fv] = $accentPool[$accentIndex % count($accentPool)];
                $accentIndex++;
            }
        }
    @endphp
    <div class="bg-white rounded shadow-lg mb-8 hover:shadow-xl transition-all duration-300" id="funnel-card" x-data="{ open: true }">
        <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100 flex items-center justify-between flex-wrap gap-2 cursor-pointer select-none"
             @click="open = !open" title="Click to collapse/expand">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-chevron-down mr-2 text-gray-400 transition-transform duration-200" :class="open ? '' : '-rotate-90'"></i>Funnel — where each variant loses people
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    @if(($funnel['source'] ?? '') === 'ab_events')
                        Event reach funnel (users reaching each step, first touch)
                        — <a href="{{ route('ab-testing.dashboard.edit', $experiment) }}" class="text-blue-600 hover:underline" @click.stop>configure step order</a>
                    @else
                        Step-level funnel · source: {{ $funnel['source'] }}
                    @endif
                </p>
            </div>
            <span class="text-xs text-gray-500">Bar = % of the arm's first step · label = step→step retention</span>
        </div>
        <div class="p-6 overflow-x-auto" x-show="open">
            <div class="grid gap-6" style="grid-template-columns: repeat({{ count($funnelVariants) }}, minmax(220px, 1fr));">
                @foreach($funnelVariants as $fv)
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-3 h-3 rounded-full inline-block" style="background: {{ $variantPalette[$fv] }}"></span>
                            <span class="font-semibold text-gray-900">{{ $fv }}</span>
                            @if($fv === 'control')
                                <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">Control</span>
                            @endif
                        </div>
                        @php
                            $base = max($steps[0]['counts'][$fv] ?? 0, 1);
                            // Retention is measured against the arm's previous
                            // REACHED step (count > 0) — arms legitimately skip
                            // other arms' events, and a structural zero is not
                            // a drop (mirrors biggestDropAfter's semantics).
                            $prevReachedCount = null;
                            $prevReachedKey = null;
                        @endphp
                        @foreach($steps as $i => $step)
                            @php
                                $count = $step['counts'][$fv] ?? 0;
                                $pctOfBase = min(100, round(($count / $base) * 100, 1));
                                $notFired = $i > 0 && $count === 0;
                                $retention = (!$notFired && $prevReachedCount !== null && $prevReachedCount > 0)
                                    ? round(($count / $prevReachedCount) * 100, 1)
                                    : null;
                                $isBiggestDrop = !$notFired && $i > 0
                                    && ($dropAfter[$fv] ?? null) !== null
                                    && ($dropAfter[$fv] ?? null) === $prevReachedKey;
                            @endphp
                            @if($i > 0)
                                <div class="pl-2 py-0.5 text-xs {{ $isBiggestDrop ? 'text-red-600 font-semibold' : 'text-gray-400' }}" data-funnel-retention="{{ $fv }}:{{ $step['key'] }}">
                                    @if($notFired)
                                        <i class="fas fa-minus mr-1"></i>not fired by this arm
                                    @else
                                        <i class="fas fa-arrow-down mr-1"></i>{{ $retention !== null ? $retention . '% continue' : '—' }}
                                        @if($isBiggestDrop)
                                            · biggest drop
                                        @endif
                                    @endif
                                </div>
                            @endif
                            @if($notFired)
                                <div class="rounded bg-gray-50 mb-0.5" title="{{ $step['label'] }}: this arm never fires this event">
                                    <div class="flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs text-gray-400 whitespace-nowrap border border-dashed border-gray-200"
                                         style="width: 45%; min-width: 130px;"
                                         data-funnel-bar="{{ $fv }}:{{ $step['key'] }}">
                                        <span class="truncate">{{ $step['label'] }}</span>
                                        <span>n/a</span>
                                    </div>
                                </div>
                            @else
                                <div class="rounded {{ $isBiggestDrop ? 'ring-2 ring-red-300' : '' }} bg-gray-50 mb-0.5" title="{{ $step['label'] }}: {{ number_format($count) }} users ({{ $pctOfBase }}% of first step)">
                                    <div class="flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs text-white whitespace-nowrap"
                                         style="background: {{ $variantPalette[$fv] }}; width: {{ max($pctOfBase, 18) }}%; min-width: 130px; opacity: {{ 0.55 + 0.45 * ($pctOfBase / 100) }};"
                                         data-funnel-bar="{{ $fv }}:{{ $step['key'] }}">
                                        <span class="truncate">{{ $step['label'] }}</span>
                                        <span class="font-semibold">{{ number_format($count) }}</span>
                                    </div>
                                </div>
                            @endif
                            @php
                                if ($i === 0 || $count > 0) {
                                    $prevReachedCount = $count;
                                    $prevReachedKey = $step['key'];
                                }
                            @endphp
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
