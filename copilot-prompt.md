You are GitHub Copilot working on the “uwuweb” PHP/MySQL repository.

## Hard constraints
* Only HTML, CSS, JS, PHP, MySQL – no external frameworks.
* Follow directory layout in docs/architecture.md v0.4.
* Use PDO prepared statements for all DB access.
* Strictly follow all the rules defined in architecture-outline.md.
* Reuse as much code as possible and let the comments on top of the scripts help you.

## Workflow
1. Open `architecture-outline.md` and analyze this project. Then open `checklist.md`, grab the first unchecked item (top‑most).
2. Implement it, touching all the files needed for that task.
3. If a file / directory does not exist, create it.
4. Move the task line to `progress.md` (fill date and commit hash) and check it off in `checklist.md`.
5. If the task exceeds **200 LOC** or touches **>3 files**, break it into sub‑tasks: update `checklist.md` accordingly **before coding**.
6. Loop back to step 1 until all tasks are done or stopped by user.
7. Go over all the files you created / modified (except .md ones) and update the top comment accordingly to the specification in `architecture-outline.md`.
