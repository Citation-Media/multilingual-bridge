# Agent Instructions for Multilingual Bridge Plugin

## Build/Lint/Test Commands

### PHP Commands
- **Lint**: `composer phpcs` (WordPress Coding Standards)
- **Static Analysis**: `composer phpstan` (PHPStan level 6)
- **Auto-fix**: `composer phpcbf` (PHP CodeSniffer fix)
- **Single Test**: PHPUnit configured but tests directory not present - run `vendor/bin/phpunit` when tests exist

### JavaScript Commands
- **Lint**: ESLint configured via `@roots/eslint-config` + WordPress rules
- **Format Check**: `npm run prettier`
- **Format Fix**: `npm run prettier:fix`
- **Build Development**: `npm run development` (Bud.js)
- **Build Production**: `npm run production` (Bud.js)

## Code Style Guidelines

### PHP Standards
- **Coding Standard**: WordPress Coding Standards (WPCS) with exclusions for file naming
- **Type Safety**: PHPStan level 6 enforced, strong typing required
- **Autoloading**: PSR-4 with namespace `Multilingual_Bridge\` mapping to `src/`
- **Imports**: Use statements at file top, fully qualified class names in code
- **Naming Conventions**:
  - Classes: PascalCase (e.g., `WPML_Post_Helper`)
  - Methods: camelCase (e.g., `get_language()`)
  - Files: underscores (e.g., `wpml-post-helper.php`)
  - Constants: UPPER_SNAKE_CASE
- **Error Handling**: Use `WP_Error` objects for WordPress-style error handling
- **Return Types**: Always declare return types, use union types (e.g., `int|WP_Post`)
- **Control Flow**: Exit early pattern - `if (!$condition) return;`
- **Documentation**: Extensive PHPDoc comments for all functions/classes/methods

### JavaScript Standards
- **Linting**: WordPress ESLint config with custom rules
- **Formatting**: Prettier with WordPress config
- **Globals**: jQuery, $, multilingual_bridge available
- **Naming**: camelCase, allow `multilingual_bridge` global

### General Principles
- **DRY**: Don't Repeat Yourself - prefer abstraction
- **Clean Code**: Readable, maintainable code following established patterns
- **Security**: Never expose secrets, validate inputs, use WordPress sanitization
- **WordPress Integration**: Use WP hooks, filters, and APIs appropriately

## Project-Specific Patterns

### WPML Integration
- Use static helper methods from `WPML_*_Helper` classes
- Always check language validity before operations
- Handle cross-language relationships carefully
- Clear WPML cache after language operations

### REST API Extensions
- Include `language_code` field in all responses
- Add `_links.translations` for multilingual content
- Validate and sanitize all inputs

### Admin Tools
- Use language debug tools for troubleshooting
- Implement bulk cleanup for orphaned content
- Follow WordPress admin UI patterns

## Copilot Instructions

### Project Overview
- This is a WordPress plugin that bridges WPML and the WordPress REST API, enabling multilingual support for headless and modern WP applications.
- Major components are in `src/`:
  - `Admin/`: Admin tools and UI
  - `Helpers/`: WPML helper functions (e.g., `WPML_Post_Helper`)
  - `REST/`: REST API extensions for language support
- Documentation for features and helpers is in `docs/` (see `REST_API/`, `Helpers/`, `Admin/`).

### Key Patterns & Conventions
- **REST API responses**: Always include a `language_code` field and `_links.translations` for multilingual content. See `docs/REST_API/language-fields-and-links.md`.
- **WPML helpers**: Use static methods from helpers (e.g., `WPML_Post_Helper::get_language($post_id)`) for language operations. See `docs/Helpers/wpml-post-helper.md`.
- **Admin tools**: Use language debug and bulk cleanup tools for managing orphaned or misconfigured content. See `docs/Admin/language-debug.md`.
- **Type safety**: PHPStan level 5 enforced. Run `composer phpstan` for static analysis.
- **Coding standards**: WordPress Coding Standards (WPCS) enforced. Use `composer phpcs` and `composer phpcbf`.
- **Documentation**: All new features must be documented in `docs/` (grouped by feature area).

### Developer Workflow
- After implementing a feature, add documentation in `docs/` (create a folder if needed).
- Use provided Composer scripts for code quality:
  - `composer phpcs` (lint)
  - `composer phpstan` (static analysis)
  - `composer phpcbf` (auto-fix)
- For REST API changes, ensure language fields and translation links are present in responses.
- For WPML operations, prefer helper classes over direct WPML API calls.

### Integration Points
- Requires WPML plugin (must be installed and activated).
- Designed for compatibility with headless WordPress, JAMstack, and external integrations.
- REST API endpoints are extended for language awareness; see `src/REST/` and related docs.

### Example Usage
```php
// Get post language
$language = WPML_Post_Helper::get_language($post_id);
// Get all translations
$translations = WPML_Post_Helper::get_language_versions($post_id);
```

### References
- See `README.md` for overview and quick start.
- See `docs/` for feature-specific documentation.
- See `src/Helpers/` for WPML helper implementations.
- See `src/REST/` for REST API extensions.

## Development Rules

Read when executing php,npm,yarn,composer commands or working with ddev local environment: @.github/instructions/local-development/wp-umbrella-backup-import.instructions.md

Read when working with WordPress REST API endpoints, validation, and sanitization: @.github/instructions/rest-api/validation-sanitization.instructions.md

Read when implementing error handling in WordPress applications: @.github/instructions/error-handling.instructions.md

Read when creating or modifying WordPress database queries: @.github/instructions/queries.instructions.md

Read when working with PHPStan static analysis: @.github/instructions/quality-assurance/phpstan.instructions.md

Read when writing WordPress code, following coding standards, and best practices: @.github/instructions/base.instructions.md