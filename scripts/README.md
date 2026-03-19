# Development Session Scripts

This directory contains helper scripts for managing development sessions and tracking progress.

---

## Scripts

### `session-check.sh` (Bash)
Main entry point for session management. Displays progress and provides reminders.

**Usage:**
```bash
./scripts/session-check.sh          # Check current progress
./scripts/session-check.sh start    # Start new session
./scripts/session-check.sh end      # End current session
```

**Features:**
- Displays overall progress statistics
- Shows phase-by-phase breakdown
- Lists tasks in progress
- Shows next upcoming tasks
- Provides pre/post session checklists

### `check-progress.php` (PHP)
Core PHP script that reads `docs/progress.json` and displays formatted progress information.

**Usage:**
```bash
php scripts/check-progress.php          # Check progress
php scripts/check-progress.php start    # Start session
php scripts/check-progress.php end      # End session
```

**Called by:** `session-check.sh`

---

## Composer Commands

These scripts are integrated into Composer for convenience:

```bash
composer session          # Same as ./scripts/session-check.sh
composer session:start    # Same as ./scripts/session-check.sh start
composer session:end      # Same as ./scripts/session-check.sh end
composer progress         # Same as php scripts/check-progress.php
```

---

## Workflow Integration

### Before Development Work

1. Run `composer session:start`
2. Review the displayed progress
3. Note the next task to work on
4. Update `docs/progress.json` - mark task as `in_progress`
5. Update `docs/PROGRESS_TRACKER.md` - mark task as 🔄
6. Create session log in `docs/session-logs/`

### After Development Work

1. Complete your code changes
2. Run `vendor/bin/pint --format agent`
3. Run tests: `php artisan test --compact`
4. Run `composer session:end`
5. Follow the checklist:
   - Update `docs/progress.json` - mark task as `completed`
   - Update `docs/PROGRESS_TRACKER.md` - mark task as ✅
   - Complete session log in `docs/session-logs/`
6. Commit with task ID: `git commit -m "feat: description [Phase X.X]"`

---

## Output Example

```
╔══════════════════════════════════════════════════════════════╗
║         MSWMS Development Progress Tracker                  ║
╚══════════════════════════════════════════════════════════════╝

📊 Overall Progress
────────────────────────────────────────────────────────────────
  Total Tasks:        0 / 48 completed
  Completion:         0.0%
  Time Spent:         0.0h / 170.0h estimated

📁 Phase Progress
────────────────────────────────────────────────────────────────
  ⬜ Phase 1: Foundation & Authentication
     [░░░░░░░░░░] 0% (0/7 tasks) - 0.0h/15.0h
  ...
```

---

## Requirements

- **PHP 8.3+** - For `check-progress.php`
- **Bash** - For `session-check.sh`
- **Composer** - For composer command integration
- **JSON extension** - Built into PHP

---

## Troubleshooting

### Scripts not executable?
```bash
chmod +x scripts/session-check.sh
chmod +x scripts/check-progress.php
```

### Composer command not found?
```bash
# Clear composer cache
composer dump-autoload
```

### JSON error?
```bash
# Validate progress.json
php -r "json_decode(file_get_contents('docs/progress.json')); echo json_last_error_msg();"
```

---

## File Structure

```
scripts/
├── session-check.sh          # Main bash wrapper
├── check-progress.php        # Core PHP progress reader
└── README.md                 # This file
```

---

## Maintenance

These scripts should be:
- ✅ Kept executable (`chmod +x`)
- ✅ Tested after any `progress.json` schema changes
- ✅ Updated if new phases are added to roadmap

---

**Created:** March 19, 2026  
**Version:** 1.0  
**Maintainer:** Development Team
