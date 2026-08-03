# Family Archive Documentation

This is the canonical entry point for product, engineering and evidence
documentation.

## Start here

- [Repository overview](../README.md)
- [Current maintenance boundary](CURRENT_BUCKET.md)
- [Capabilities](CAPABILITIES.md)
- [System overview](architecture/SYSTEM_OVERVIEW.md)
- [Threat model](THREAT_MODEL.md)

## Release and evidence

- [Release history](RELEASE_HISTORY.md)
- [v3.9.0 release notes](release-notes/v3.9.0.md)
- [Roadmap](ROADMAP.md)
- [Screenshot evidence guide](screenshot-groups/README.md)
- [Build and evidence process](BUILD_AND_EVIDENCE.md)

## Engineering

- [Development process](DEVELOPMENT_PROCESS.md)
- [Product positioning](PRODUCT_POSITIONING.md)
- [Security policy](../SECURITY.md)
- [Project state (machine-readable)](project-state.json)

The machine-readable project state and `config/release.php` must agree. Run
`composer release:verify` before a release or repository handoff.
