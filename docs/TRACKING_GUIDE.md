# Progress Tracking System - Quick Reference Guide

## Overview

This tracking system helps you monitor development progress across all phases of the MSWMS project. It consists of three main files:

| File | Purpose | Update Frequency |
|------|---------|------------------|
| `PROGRESS_TRACKER.md` | Main progress dashboard | End of each session |
| `SESSION_LOG_TEMPLATE.md` | Individual session documentation | Each development session |
| `progress.json` | Machine-readable progress data | End of each session |

---

## How to Use

### Starting a Development Session

1. **Copy the session template:**
   ```bash
   cp docs/SESSION_LOG_TEMPLATE.md docs/session-logs/session-002.md
   ```

2. **Update `progress.json`:**
   - Change task status from `pending` → `in_progress` when starting
   - Set `startedAt` timestamp
   - Update phase status to `in_progress`

3. **Update `PROGRESS_TRACKER.md`:**
   - Mark task as "🔄 In Progress"
   - Update phase progress table

### During the Session

Track your work in the session log file:
- Record commands executed
- Note files created/modified
- Document issues and solutions
- Track time spent on each task

### Ending a Development Session

1. **Update task status in `progress.json`:**
   ```json
   {
     "1.1": {
       "status": "completed",
       "timeSpent": 1.5,
       "completedAt": "2026-03-19T15:30:00Z",
       "notes": "Sanctum installed and configured"
     }
   }
   ```

2. **Update `PROGRESS_TRACKER.md`:**
   - Mark task as ✅ Completed
   - Update time spent
   - Check off deliverables
   - Update phase progress percentage

3. **Complete the session log:**
   - Fill in end time and duration
   - Document work completed
   - Add test results
   - Plan next session

4. **Update statistics:**
   - Recalculate completion percentages
   - Update cumulative time spent
   - Update burndown metrics

---

## Status Values

### Task Status
| Value | Meaning | When to Use |
|-------|---------|-------------|
| `pending` | ⬜ Not started | Default state |
| `in_progress` | 🔄 Active work | Currently working on |
| `completed` | ✅ Done | Task finished and tested |
| `on_hold` | ⏸️ Paused | Blocked or deprioritized |

### Phase Status
| Value | Meaning |
|-------|---------|
| `not_started` | 🔴 No tasks started |
| `in_progress` | 🔄 At least one task active |
| `completed` | ✅ All tasks done |
| `pending` | 🟡 Waiting on previous phase |

---

## Quick Update Commands

### Calculate Phase Progress
```bash
# Count completed tasks in phase
jq '.phases.phase1.tasks | to_entries | map(select(.value.status == "completed")) | length' docs/progress.json
```

### Total Time Spent
```bash
# Sum time across all tasks
jq '[.phases | to_entries[].value.timeSpent] | add' docs/progress.json
```

### Overall Completion
```bash
# Calculate percentage
jq '(.statistics.completedTasks / .statistics.totalTasks * 100) | floor' docs/progress.json
```

---

## Session Log Best Practices

### What to Document
- ✅ **Commands executed** - Artisan commands, composer, etc.
- ✅ **Files created/modified** - Full paths
- ✅ **Test results** - Pass/fail output
- ✅ **Time tracking** - Per task
- ✅ **Issues encountered** - And how resolved
- ✅ **Key decisions** - With rationale

### What to Skip
- ❌ Obvious changes (e.g., "updated line 10")
- ❌ Trial and error attempts
- ❌ Temporary debug code

---

## Example Updates

### Example 1: Completing Task 1.1 (Install Sanctum)

**In `progress.json`:**
```json
{
  "1.1": {
    "status": "completed",
    "timeSpent": 1,
    "completedAt": "2026-03-19T11:00:00Z",
    "notes": "Installed via composer, configured guards"
  }
}
```

**In `PROGRESS_TRACKER.md`:**
```markdown
| 1.1 | Install Laravel Sanctum | ✅ Completed | 2026-03-19 | 2026-03-19 | 1h | API token authentication |
```

**In session log:**
```markdown
### Commands Executed
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

---

## Tips for Effective Tracking

1. **Update frequently** - Don't wait until end of session
2. **Be specific** - Note exact changes and reasons
3. **Track all time** - Include debugging and testing
4. **Link related items** - Reference task IDs in notes
5. **Keep JSON valid** - Use a JSON validator
6. **Commit often** - Track progress in git commits

---

## File Locations

```
docs/
├── PROGRESS_TRACKER.md          # Main dashboard
├── SESSION_LOG_TEMPLATE.md      # Template for sessions
├── progress.json                # Machine-readable data
├── TRACKING_GUIDE.md            # This file
└── session-logs/                # Individual session logs
    ├── session-001.md
    └── session-002.md
```

---

## Integration with Git

Reference task IDs in commit messages:
```bash
git commit -m "feat: install Laravel Sanctum [Phase 1.1]"
git commit -m "test: add auth tests [Phase 1.7]"
```

---

## Maintenance

- **Weekly:** Review overall progress and adjust estimates
- **Per Session:** Update all three tracking files
- **Per Phase:** Archive completed phase deliverables
- **Project End:** Compile final metrics and lessons learned

---

**Last Updated:** March 19, 2026  
**Maintainer:** Development Team
