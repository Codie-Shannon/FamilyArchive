# Development Process and Tooling

FamilyArchive is a human-owned, AI-assisted software project.

The maintainer defines the product requirements, roadmap, architecture,
preservation policy, privacy boundaries and acceptance criteria. The maintainer
also reviews proposed changes, decides what enters the repository, runs the
manual evidence process and remains responsible for the resulting software.

## AI-Assisted Engineering

OpenAI Codex and ChatGPT have been used as engineering copilots for:

- implementation acceleration and repetitive scaffolding;
- code review, debugging and alternative-design analysis;
- test planning and validation suggestions;
- static-analysis and compatibility diagnostics; and
- organizing technical documentation.

Tool suggestions are treated as untrusted until they are inspected against the
project requirements and verified by the repository's tests and evidence
process. The tools do not approve archive decisions, define preservation
policy, accept evidence or determine release closure.

## Human Ownership

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
