---
description: Fix bugs from the bugs tracking directory
---

Fix bugs documented in the bugs tracking directory (`docs/bugs/`).

## Usage

```
/bugfix [bug-id]
```

If no bug ID is provided, list all open bugs and prompt the user to select one.

## Workflow

1. **Locate the bug document** in `docs/bugs/` matching the given bug ID (e.g., `BUG-001`, `B-001`).

2. **Read the bug document** to understand:
   - The problem description
   - Affected files
   - Expected fix
   - Verification steps

3. **Implement the fix** for each sub-bug (B-001, B-002, etc.) listed in the document:
   - Read the affected file first
   - Apply the fix as specified
   - Follow project conventions (Laravel Pint formatting, explicit types, etc.)

4. **Run affected tests**:
   ```bash
   php artisan test --compact --filter=BatchManagement
   php artisan test --compact tests/Feature/FifoInventoryTest.php
   ```
   If no test exists for the affected area, create one.

5. **Format code**:
   ```bash
   vendor/bin/pint --format agent
   ```

6. **Update the bug document**:
   - Mark each fixed bug's status from `Open` → `Fixed`
   - Add a "Fix Notes" section with what was changed and any deviations from the plan

7. **Generate a conventional commit** using `/cc` command.

## Rules

- Fix bugs in the priority order specified in the document (Critical → High → Medium → Low)
- Always read the full affected file before making changes
- Never skip tests — if no test exists, write one
- If a fix requires a decision not covered in the bug doc, ask the user before proceeding
- After fixing, verify the fix works by running the verification steps from the bug document
