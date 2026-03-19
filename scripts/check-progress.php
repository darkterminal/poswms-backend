#!/usr/bin/env php
<?php

/**
 * Development Session Progress Tracker Script
 *
 * This script helps manage development sessions by:
 * 1. Displaying current progress summary
 * 2. Showing active/in-progress tasks
 * 3. Prompting for session start/end actions
 *
 * Usage:
 *   php scripts/check-progress.php          // Check current progress
 *   php scripts/check-progress.php start    // Start new session
 *   php scripts/check-progress.php end      // End current session
 */
$progressFile = __DIR__.'/../docs/progress.json';
$trackerFile = __DIR__.'/../docs/PROGRESS_TRACKER.md';
$sessionLogsDir = __DIR__.'/../docs/session-logs';

if (! file_exists($progressFile)) {
    echo "❌ Progress file not found: $progressFile\n";
    exit(1);
}

$progress = json_decode(file_get_contents($progressFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo '❌ Invalid JSON in progress file: '.json_last_error_msg()."\n";
    exit(1);
}

// Display header
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         MSWMS Development Progress Tracker                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Display overall statistics
$stats = $progress['statistics'];
echo "📊 Overall Progress\n";
echo "────────────────────────────────────────────────────────────────\n";
echo sprintf("  Total Tasks:        %d / %d completed\n", $stats['completedTasks'], $stats['totalTasks']);
echo sprintf("  Completion:         %.1f%%\n", $stats['completionPercentage']);
echo sprintf("  Time Spent:         %.1fh / %.1fh estimated\n", $stats['totalTimeSpent'], $stats['totalEstimatedHours']);
echo sprintf("  Last Updated:       %s\n", $progress['lastUpdated']);
echo "\n";

// Display phase progress
echo "📁 Phase Progress\n";
echo "────────────────────────────────────────────────────────────────\n";
$phaseNames = [
    'phase1' => 'Phase 1: Foundation & Authentication',
    'phase2' => 'Phase 2: Core Entities',
    'phase3' => 'Phase 3: Inventory Management',
    'phase4' => 'Phase 4: Order Management',
    'phase5' => 'Phase 5: Multi-Level Pricing',
    'phase6' => 'Phase 6: Reporting & Analytics',
    'phase7' => 'Phase 7: Advanced Features',
    'phase8' => 'Phase 8: Production Readiness',
];

foreach ($phaseNames as $phaseKey => $phaseName) {
    $phase = $progress['phases'][$phaseKey];
    $taskCount = count($phase['tasks']);
    $completedCount = count(array_filter($phase['tasks'], fn ($t) => $t['status'] === 'completed'));
    $inProgressCount = count(array_filter($phase['tasks'], fn ($t) => $t['status'] === 'in_progress'));
    $percentage = $taskCount > 0 ? ($completedCount / $taskCount * 100) : 0;

    $statusIcon = '⬜';
    if ($phase['status'] === 'in_progress') {
        $statusIcon = '🔄';
    }
    if ($phase['status'] === 'completed') {
        $statusIcon = '✅';
    }

    $progressBar = str_repeat('█', (int) ($percentage / 10)).str_repeat('░', 10 - (int) ($percentage / 10));

    echo sprintf("  %s %s\n", $statusIcon, $phaseName);
    echo sprintf("     [%s] %d%% (%d/%d tasks) - %.1fh/%.1fh\n",
        $progressBar,
        round($percentage),
        $completedCount,
        $taskCount,
        $phase['timeSpent'],
        $phase['estimatedHours']
    );

    if ($inProgressCount > 0) {
        echo sprintf("     🔄 %d task(s) in progress\n", $inProgressCount);
    }
    echo "\n";
}

// Show in-progress tasks
echo "🔍 Tasks In Progress\n";
echo "────────────────────────────────────────────────────────────────\n";
$hasInProgress = false;
foreach ($progress['phases'] as $phaseKey => $phase) {
    foreach ($phase['tasks'] as $task) {
        if ($task['status'] === 'in_progress') {
            $hasInProgress = true;
            echo sprintf("  • [%s] %s - %s\n",
                $task['id'],
                $task['name'],
                $phase['name']
            );
            if ($task['notes']) {
                echo sprintf("    Notes: %s\n", $task['notes']);
            }
        }
    }
}
if (! $hasInProgress) {
    echo "  No tasks currently in progress.\n";
}
echo "\n";

// Show pending tasks for current phase
$currentPhase = null;
foreach ($progress['phases'] as $phaseKey => $phase) {
    if ($phase['status'] === 'in_progress' || $phase['status'] === 'not_started') {
        $currentPhase = $phase;
        break;
    }
}

if ($currentPhase) {
    echo "📋 Next Upcoming Tasks\n";
    echo "────────────────────────────────────────────────────────────────\n";
    $pendingTasks = array_filter($currentPhase['tasks'], fn ($t) => $t['status'] === 'pending');
    $showCount = min(5, count($pendingTasks));
    $counter = 0;
    foreach ($pendingTasks as $task) {
        if ($counter >= $showCount) {
            break;
        }
        echo sprintf("  %d. [%s] %s (%.1fh)\n",
            $counter + 1,
            $task['id'],
            $task['name'],
            $task['estimatedHours']
        );
        $counter++;
    }
    echo "\n";
}

// Session management
$action = $argv[1] ?? null;

if ($action === 'start') {
    echo "🚀 Starting New Development Session\n";
    echo "────────────────────────────────────────────────────────────────\n";

    // Find next session number
    $sessionFiles = glob($sessionLogsDir.'/session-*.md');
    $nextSessionNum = count($sessionFiles) + 1;
    $sessionFile = sprintf('%s/session-%03d.md', $sessionLogsDir, $nextSessionNum);

    echo "  Next session number: #$nextSessionNum\n";
    echo "  Session file: $sessionFile\n";
    echo "\n";
    echo "  Recommended actions:\n";
    echo "  1. Copy template: cp docs/SESSION_LOG_TEMPLATE.md $sessionFile\n";
    echo "  2. Update progress.json task status to 'in_progress'\n";
    echo "  3. Update PROGRESS_TRACKER.md with 🔄 status\n";
    echo "\n";
} elseif ($action === 'end') {
    echo "✅ Ending Development Session\n";
    echo "────────────────────────────────────────────────────────────────\n";
    echo "\n";
    echo "  Remember to:\n";
    echo "  1. Complete your session log in docs/session-logs/\n";
    echo "  2. Update task status in progress.json to 'completed'\n";
    echo "  3. Update PROGRESS_TRACKER.md with ✅ and time spent\n";
    echo "  4. Run: vendor/bin/pint --format agent\n";
    echo "  5. Run tests: php artisan test --compact\n";
    echo "\n";
} else {
    echo "💡 Usage Tips\n";
    echo "────────────────────────────────────────────────────────────────\n";
    echo "  php scripts/check-progress.php          // Check current progress\n";
    echo "  php scripts/check-progress.php start    // Start new session\n";
    echo "  php scripts/check-progress.php end      // End current session\n";
    echo "\n";
}

echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
