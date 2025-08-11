---
applyTo: '**'
---
# PHPStan Quality Assurance Rules

## Purpose
Comprehensive configuration and patterns for static analysis in WordPress development using PHPStan.

## Guidelines
- Use PHPStan level 5 or higher for WordPress projects.
- Add custom type hints for WordPress core functions and hooks.
- Use docblocks for array shapes and custom types.
- Exclude vendor and build directories from analysis.
- Integrate with Composer scripts for CI/CD.
- For REST API, ensure response typings are documented and validated.
- Prefer strong typing and explicit return types in all PHP code.

## Example Configuration
```neon
parameters:
    level: 5
    paths:
        - src
    excludePaths:
        - vendor
        - build
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    checkExplicitMixed: true
```
