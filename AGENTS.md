# Agent Instructions — eQual Multi-Package Repository

## Context

This repository contains one or more **eQual packages**. It is not a standalone application.

It is designed to be placed inside an eQual environment by placing its content into the `/packages/` directory of the installation.



## Important

This repository does **not include the full eQual framework**.

- The `core` package is provided separately.
- The execution environment, ORM behavior, controllers and runtime are defined by eQual.

---

## Source of Instructions

If a parent repository exists and contains an AGENTS file (`../AGENTS.md`):

→ **You must follow the instructions defined in that root `AGENTS.md`**

This file only provides local context for this repository.

---

## When no parent `AGENTS.md` is available

Apply standard eQual development rules:

### Package scope

- Work within a **single package at a time**
- Do not modify other packages unless explicitly required
- Never modify the `core` package

### Multi-layer consistency

Any change may impact multiple layers.

Always consider:

- `classes/` (ORM entities)
- `views/` (UI definitions)
- `i18n/` (translations)
- `actions/` (controllers)
- `data/` (data providers)

Do not assume that modifying a single file is sufficient.

---

## Typical workflow

When performing a task:

1. Identify the target package
2. Identify the impacted entity or feature
3. Apply the change in the ORM class (`classes/`)
4. Update related views (`views/`)
5. Update translations (`i18n/`)
6. Update actions or data handlers if required
7. Validate consistency across all layers

---

## Constraints

- Code identifiers, variables and comments must be written in English
- Follow existing conventions found in the package
- Do not introduce new patterns if similar ones already exist
- Keep changes minimal and scoped to the task
- Avoid modifying unrelated files

---

## Notes

- Packages in this repository may be **independent or loosely coupled**
- Each package may define its own namespace and internal structure
- Always inspect nearby files before implementing changes

