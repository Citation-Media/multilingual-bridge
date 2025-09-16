---
applyTo: '**'
---
# React in WordPress — **Admin Settings Pages** vs **General React Apps**

**Purpose**: Provide clear, split guidance for (A) building modern **custom settings pages** in wp‑admin using official WordPress React components and (B) using **React generally** in WordPress (front‑end UIs or other admin tools) — **without referencing bundlers or `@wordpress/scripts`**.

---

## 0) Scope & Core Packages (no‑build usage)

Use WordPress’ built‑in script/style handles and global `wp.*` namespaces. You don’t import modules; you depend on core handles and access APIs via globals.

**Core script handles**: `wp-element`, `wp-dom-ready`, `wp-components`, `wp-api-fetch`, `wp-data`, `wp-i18n`, `wp-notices`

**Core style handle**: `wp-components`

Access in JS via globals, e.g. `wp.element`, `wp.domReady`, `wp.components`, `wp.apiFetch`, `wp.data`, `wp.i18n`.

---

# A) **Custom Settings Pages** (wp‑admin)

Use this track when you’re building a **plugin settings UI** in the WordPress admin.

### A1) Admin page container (PHP)

Add a submenu (e.g. under **Settings**) and render a minimal mount node:

```php
add_action('admin_menu', function () {
  add_options_page(
    __('<Plugin Title>', '<text-domain>'),
    __('<Plugin Title>', '<text-domain>'),
    'manage_options',
    '<slug>',
    function () {
      printf('<div class="wrap" id="<slug>-settings">%s</div>', esc_html__('Loading…','<text-domain>'));
    }
  );
});
```

### A2) Enqueue only on your screen (PHP)

Depend on WordPress’ script/style handles and your own static assets (no bundler assumptions).

```php
add_action('admin_enqueue_scripts', function ($admin_page) {
  if ('settings_page_<slug>' !== $admin_page) return;

  // Core dependencies provided by WordPress
  $deps = [ 'wp-element', 'wp-dom-ready', 'wp-components', 'wp-api-fetch', 'wp-data', 'wp-i18n', 'wp-notices' ];

  // Your plain JS/CSS files inside the plugin
  $js  = plugins_url('assets/admin.js', __FILE__);
  $css = plugins_url('assets/admin.css', __FILE__);

  // Versioning can be static or filemtime-based
  $ver_js  = file_exists(__DIR__.'/assets/admin.js')  ? filemtime(__DIR__.'/assets/admin.js')  : '1.0.0';
  $ver_css = file_exists(__DIR__.'/assets/admin.css') ? filemtime(__DIR__.'/assets/admin.css') : '1.0.0';

  wp_enqueue_script('<slug>-admin', $js, $deps, $ver_js, true);
  wp_enqueue_style('<slug>-admin', $css, [ 'wp-components' ], $ver_css);
});
```

### A3) React bootstrap (JS, globals only)

```js
/* global wp */
wp.domReady(function () {
  const mount = document.getElementById('<slug>-settings');
  if (!mount) return;

  const { createElement, createRoot, useState, useEffect } = wp.element;

  function SettingsPage() {
    return createElement('div', null, 'Placeholder');
  }

  createRoot(mount).render(createElement(SettingsPage));
});
```

### A4) Build the UI with WordPress Components (JS, globals)

Use `wp.components` and `wp.i18n` directly; no imports.

```js
const { Panel, PanelBody, PanelRow, TextareaControl, ToggleControl, FontSizePicker, Button, NoticeList } = wp.components;
const { __ } = wp.i18n;
```

Structure your UI with `Panel/PanelBody/PanelRow` and wire inputs to state.

### A5) State + persistence via Site Settings REST

Create a small helper using globals: `wp.apiFetch`, `wp.element` hooks.

**PHP**

```php
add_action('init', function () {
  $default = [ 'message' => __('Hello!','<text-domain>'), 'display' => true, 'size' => 'medium' ];
  $schema  = [ 'type' => 'object', 'properties' => [
    'message' => ['type'=>'string'],
    'display' => ['type'=>'boolean'],
    'size'    => ['type'=>'string','enum'=>['small','medium','large','x-large']],
  ]];
  register_setting('options', '<option_key>', [
    'type' => 'object', 'default' => $default, 'show_in_rest' => [ 'schema' => $schema ],
  ]);
});
```

**JS**

```js
/* global wp */
const { useState, useEffect } = wp.element;

function useSettings() {
  const [message, setMessage] = useState('');
  const [display, setDisplay] = useState(true);
  const [size, setSize]       = useState('medium');

  useEffect(() => {
    wp.apiFetch({ path: '/wp/v2/settings' }).then((s) => {
      const opt = s.<option_key> || {};
      setMessage(opt.message ?? '');
      setDisplay(Boolean(opt.display));
      setSize(opt.size ?? 'medium');
    });
  }, []);

  function save() {
    return wp.apiFetch({
      path: '/wp/v2/settings', method: 'POST',
      data: { <option_key>: { message, display, size } },
    });
  }

  return { message, setMessage, display, setDisplay, size, setSize, save };
}
```

### A6) Save UX (notices)

```js
/* global wp */
const { dispatch, select } = wp.data;
const { NoticeList } = wp.components;

function showSaved() {
  dispatch('core/notices').createSuccessNotice('Settings saved.');
}
```

Render `<NoticeList />` somewhere in your page and call `showSaved()` after a successful save.

### A7) Styles

Provide a small `assets/admin.css` to tweak layout (keep it narrow, accessible). You can rely on WordPress’ `wp-components` styles for base styling.

### A8) Front‑end output (optional)

Output configured UI on the front end using a template hook like `wp_body_open`.

```php
add_action('wp_body_open', function () {
  $opt = get_option('<option_key>');
  if (empty($opt['display'])) return;
  echo '<div class="my-announcement">'.esc_html($opt['message']).'</div>';
});
```

### A9) I18n & Auth

Wrap strings in `__()` and load translations for your script via `wp_set_script_translations('<slug>-admin','<text-domain>')`. The `/wp/v2/settings` endpoint requires an authenticated admin; wp‑admin provides the nonce automatically for `wp.apiFetch`.

---

# B) **General React in WordPress** (beyond settings pages)

Use this track for React UIs on the **front end** or other **admin tools** screens — still without bundler assumptions.

### B1) Where to mount React

* **Front end**: create a container (shortcode, `wp_body_open`, block render callback) and mount your app there.
* **Other admin tools**: register a top‑level or tools page; same pattern as Track A, but not tied to settings.

### B2) Script loading & dependencies (no bundler)

* Enqueue your plain JS and CSS and depend on WordPress’ handles. Example deps: `['wp-element','wp-components','wp-i18n']` (add `wp-api-fetch`, `wp-data`, etc. as needed).
* Don’t load another copy of React; rely on `wp-element` and use the `wp.element` global.

### B3) Using WordPress Components generally

* You can use `wp.components` anywhere. Ensure `wp-components` styles are present (enqueue the style handle or your own minimal CSS).

### B4) Data access patterns

* Read/write via the REST API. For authenticated front‑end writes, create custom REST routes with strict `permission_callback` and pass a nonce (e.g., via `wp_create_nonce('wp_rest')`) to `wp.apiFetch` headers.

### B5) Performance & delivery (no build‑specific advice)

* Mark scripts `in_footer` and keep them lean; lazy‑initialize using `IntersectionObserver` for widgets offscreen.
* Use small inline boot data via `wp_add_inline_script()` to avoid an initial fetch when reasonable.

### B6) Minimal front‑end enqueue example

```php
add_action('wp_enqueue_scripts', function () {
  $deps = [ 'wp-element', 'wp-components', 'wp-i18n' ];
  $js   = plugins_url('assets/frontend.js', __FILE__);
  $css  = plugins_url('assets/frontend.css', __FILE__);
  $verj = file_exists(__DIR__.'/assets/frontend.js')  ? filemtime(__DIR__.'/assets/frontend.js')  : '1.0.0';
  $verc = file_exists(__DIR__.'/assets/frontend.css') ? filemtime(__DIR__.'/assets/frontend.css') : '1.0.0';
  wp_enqueue_script('<slug>-fe', $js, $deps, $verj, true);
  wp_enqueue_style('<slug>-fe', $css, [ 'wp-components' ], $verc);
});
```

### B7) Mounting on the front end (JS, globals)

```js
/* global wp */
(function mount() {
  const el = document.getElementById('<slug>-app');
  if (!el) return;
  const { createRoot, createElement } = wp.element;
  function App(){ return createElement('div', null, 'Hello from the front end'); }
  createRoot(el).render(createElement(App));
})();
```