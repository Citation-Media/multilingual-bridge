# Copilot Instructions for Multilingual Bridge Plugin

## Project Overview
- This is a WordPress plugin that bridges WPML and the WordPress REST API, enabling multilingual support for headless and modern WP applications.
- Major components are in `src/`:
  - `Admin/`: Admin tools and UI
  - `Helpers/`: WPML helper functions (e.g., `WPML_Post_Helper`)
  - `REST/`: REST API extensions for language support
- Documentation for features and helpers is in `docs/` (see `REST_API/`, `Helpers/`, `Admin/`).

## Key Patterns & Conventions
- **REST API responses**: Always include a `language_code` field and `_links.translations` for multilingual content. See `docs/REST_API/language-fields-and-links.md`.
- **WPML helpers**: Use static methods from helpers (e.g., `WPML_Post_Helper::get_language($post_id)`) for language operations. See `docs/Helpers/wpml-post-helper.md`.
- **Admin tools**: Use language debug and bulk cleanup tools for managing orphaned or misconfigured content. See `docs/Admin/language-debug.md`.
- **Type safety**: PHPStan level 5 enforced. Run `composer phpstan` for static analysis.
- **Coding standards**: WordPress Coding Standards (WPCS) enforced. Use `composer phpcs` and `composer phpcbf`.
- **Documentation**: All new features must be documented in `docs/` (grouped by feature area).

## Developer Workflow
- After implementing a feature, add documentation in `docs/` (create a folder if needed).
- Use provided Composer scripts for code quality:
  - `composer phpcs` (lint)
  - `composer phpstan` (static analysis)
  - `composer phpcbf` (auto-fix)
- For REST API changes, ensure language fields and translation links are present in responses.
- For WPML operations, prefer helper classes over direct WPML API calls.

## Integration Points
- Requires WPML plugin (must be installed and activated).
- Designed for compatibility with headless WordPress, JAMstack, and external integrations.
- REST API endpoints are extended for language awareness; see `src/REST/` and related docs.

## Example Usage
```php
// Get post language
$language = WPML_Post_Helper::get_language($post_id);
// Get all translations
$translations = WPML_Post_Helper::get_language_versions($post_id);
```

## References
- See `README.md` for overview and quick start.
- See `docs/` for feature-specific documentation.
- See `src/Helpers/` for WPML helper implementations.
- See `src/REST/` for REST API extensions.

---

If any section is unclear or missing, please provide feedback to improve these instructions.
