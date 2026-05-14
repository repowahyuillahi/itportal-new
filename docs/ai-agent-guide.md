# AI Agent Guide

## Prompt For Claude Opus 4.7

```text
You are Claude Opus 4.7 implementing ITPortal.

First read:
1. CLAUDE.md
2. README.md
3. .claude/skills/itportal/SKILL.md
4. docs/implementation-plan.md
5. docs/php-native-patterns.md
6. docs/implementation-checklist.md
7. docs/source-materials.md
8. docs/data-model.md
9. docs/api-reference.md
10. docs/routes-and-controllers.md
11. docs/export-report-spec.md

Build ITPortal V1 as a PHP native modular internal IT Helpdesk and Support
Maintenance Report app.

Locked stack:
- PHP native modular
- MySQL/MariaDB
- server-rendered PHP views
- responsive CSS
- minimal vanilla JavaScript
- Composer only for autoload and small libraries
- Excel/PDF export

Do not use Laravel, Next.js, React app, or Go.
Do not build dealer login, automatic monitoring, or PPT export in V1.

Start at Phase 1. Create the PHP foundation and keep code readable.
Treat Excel/CSV/PPTX files in the project root as source materials. Inspect and
dry-run map them before importing data.
After each task, report files changed, behavior added, checks run, gaps, and
next step.
```

## Claude Should Report

- Phase completed.
- Files changed.
- How to run.
- Checks/tests.
- Known gaps.
- Next task.
