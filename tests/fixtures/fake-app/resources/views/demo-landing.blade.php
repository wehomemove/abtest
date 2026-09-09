<?php /* Fixture for CleanupScanServiceTest */ ?>
@php $variant = $abVariant ?? 'variant_b'; @endphp
@if ($variant === 'variant_c')
    <p>demo_landing_v1 CTA-only arm</p>
@else
    <p>Postcode hero for 'variant_b'</p>
@endif
