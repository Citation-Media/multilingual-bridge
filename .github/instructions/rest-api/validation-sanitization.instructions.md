---
applyTo: '**'
---
# REST API Validation & Sanitization Rules

## Purpose
Best practices for validating and sanitizing REST API requests and responses in WordPress.

## Guidelines
- ALWAYS extend `WPRESTController` for custom REST endpoints.
- Validate all incoming data using WordPress core functions (`sanitize_text_field`, `sanitize_email`, etc.).
- Use parameter schemas for endpoint registration.
- Return errors using `WP_Error` with meaningful codes and messages.
- Document all endpoint parameters and response shapes.
- Use early returns for validation failures.
- Ensure all responses are internationalized using `__()` or `_e()`.

## Example
```php
register_rest_route('myplugin/v1', '/item', [
    'methods' => 'POST',
    'callback' => [$this, 'create_item'],
    'args' => [
        'name' => [
            'required' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function($param) {
                return !empty($param);
            },
        ],
    ],
]);
```
