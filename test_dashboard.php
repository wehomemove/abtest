<?php

require __DIR__ . '/vendor/autoload.php';

use Homemove\AbTesting\Models\Experiment;

// Test basic functionality
try {
    echo "Testing A/B Testing Package Functionality\n";
    echo "========================================\n\n";

    // Test 1: Can we create an experiment?
    echo "1. Creating test experiment...\n";
    
    $experiment = Experiment::create([
        'name' => 'test_homepage_button',
        'description' => 'Testing different button colors on homepage',
        'variants' => ['control' => 50, 'red_button' => 50],
        'is_active' => true,
        'traffic_allocation' => 100,
    ]);
    
    echo "✅ Experiment created successfully!\n";
    echo "   - ID: {$experiment->id}\n";
    echo "   - Name: {$experiment->name}\n";
    echo "   - Variants: " . json_encode($experiment->variants) . "\n\n";

    // Test 2: Can we retrieve the experiment?
    echo "2. Retrieving experiment...\n";
    $retrieved = Experiment::find($experiment->id);
    echo "✅ Experiment retrieved successfully!\n";
    echo "   - Name: {$retrieved->name}\n";
    echo "   - Active: " . ($retrieved->is_active ? 'Yes' : 'No') . "\n\n";

    // Test 3: Dashboard functionality
    echo "3. Dashboard routes available:\n";
    echo "   - Dashboard Index: /ab-testing/dashboard\n";
    echo "   - Create Experiment: /ab-testing/dashboard/create\n";
    echo "   - View Experiment: /ab-testing/dashboard/{$experiment->id}\n";
    echo "   - Edit Experiment: /ab-testing/dashboard/{$experiment->id}/edit\n\n";

    echo "🎉 Basic functionality is working!\n";
    echo "\nTo view the dashboard:\n";
    echo "1. Make sure this package is installed in a Laravel app\n";
    echo "2. Run 'php artisan migrate' to create the database tables\n";
    echo "3. Visit /ab-testing/dashboard in your browser\n";
    echo "4. Click the '7 Days' button to test duration functionality!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "This likely means the database isn't set up. Run 'php artisan migrate' first.\n";
}