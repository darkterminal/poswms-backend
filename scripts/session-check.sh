#!/bin/bash

# Development Session Helper Script
# This script checks progress and reminds you to update tracking before/after work
#
# Usage:
#   ./scripts/session-check.sh          # Check progress
#   ./scripts/session-check.sh start    # Start new session
#   ./scripts/session-check.sh end      # End current session

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  📋 MSWMS Development Session Check"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Check if progress.json exists
if [ ! -f "$PROJECT_ROOT/docs/progress.json" ]; then
    echo "❌ Error: docs/progress.json not found!"
    echo "   Please ensure the progress tracking system is set up."
    exit 1
fi

# Display current progress using PHP script
php "$SCRIPT_DIR/check-progress.php" "$@"

# Show reminder based on action
ACTION="${1:-check}"

if [ "$ACTION" = "start" ]; then
    echo "⏰ PRE-SESSION REMINDER"
    echo "─────────────────────────────────────────────────────────────"
    echo "  Before starting work:"
    echo "  ✓ Review the task you're about to work on"
    echo "  ✓ Check DEVELOPMENT_ROADMAP.md for task details"
    echo "  ✓ Update progress.json: status → 'in_progress'"
    echo "  ✓ Create session log: docs/session-logs/session-XXX.md"
    echo ""
elif [ "$ACTION" = "end" ]; then
    echo "✅ POST-SESSION CHECKLIST"
    echo "─────────────────────────────────────────────────────────────"
    echo "  Before ending your session:"
    echo "  "
    echo "  Code Quality:"
    echo "  □ Run Pint: vendor/bin/pint --format agent"
    echo "  □ Run tests: php artisan test --compact --filter=YourTest"
    echo "  "
    echo "  Progress Tracking:"
    echo "  □ Update progress.json (completed tasks)"
    echo "  □ Update PROGRESS_TRACKER.md"
    echo "  □ Complete session log in docs/session-logs/"
    echo "  □ Commit changes with task ID in message"
    echo ""
else
    echo "📖 QUICK REFERENCE"
    echo "─────────────────────────────────────────────────────────────"
    echo "  Start session:  ./scripts/session-check.sh start"
    echo "  Check progress: ./scripts/session-check.sh"
    echo "  End session:    ./scripts/session-check.sh end"
    echo ""
    echo "  Documentation:"
    echo "  • docs/DEVELOPMENT_ROADMAP.md - Task specifications"
    echo "  • docs/PROGRESS_TRACKER.md - Progress dashboard"
    echo "  • docs/TRACKING_GUIDE.md - Usage instructions"
    echo ""
fi

echo "═══════════════════════════════════════════════════════════════"
echo ""
