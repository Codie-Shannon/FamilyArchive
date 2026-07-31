# Development Process and Tooling

FamilyArchive is a maintainer-owned software project.

The maintainer defines the product requirements, roadmap, architecture,
preservation policy, privacy boundaries and acceptance criteria. The maintainer
reviews every change, decides what enters the repository, runs the manual
evidence process and remains responsible for the resulting software.

## Engineering Workflow

Development work uses repeatable repository tooling for:

- implementation and refactoring;
- code review and debugging;
- automated and manual test planning;
- static-analysis and compatibility diagnostics; and
- technical documentation.

Changes are inspected against project requirements and verified by the
repository's tests and evidence process before acceptance. Tool output never
approves archive decisions, defines preservation policy, accepts evidence or
determines release closure.

## Maintainer Ownership

The maintainer retains responsibility for:

- requirements, scope and roadmap decisions;
- architecture and data-model choices;
- security, privacy and preservation boundaries;
- accepting, revising or rejecting implementation proposals;
- reviewing fictional demonstration data and screenshots;
- running and interpreting validation results; and
- release, maintenance and repository decisions.

This distinction matters for an archive system: consequential decisions remain
human-reviewed even when engineering tools help accelerate implementation.

## Verification

Repository changes are accepted only after proportionate review and repeatable
validation. The standard suite includes:

```bash
composer test
npm run build
composer audit
npm audit --audit-level=high
```

Roadmap groups also require requirement-specific tests, privacy inspection and
human approval of their evidence set before closure.
