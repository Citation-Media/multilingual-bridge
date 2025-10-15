# WPML User Helper Functions

The Multilingual Bridge plugin provides a set of static helper functions to simplify common WPML user and capability operations, particularly for managing translation permissions without requiring specific WordPress roles.

## Overview

The `WPML_User_Helper` class provides convenient methods for:

- Adding translation manager capabilities to individual users
- Bulk adding translation manager capabilities to all users in a role
- Checking if users have translation manager capabilities
- Removing translation manager capabilities from users
- Automatic synchronization with WPML's Advanced Translation Editor (ATE)

All methods are static and can be called directly without instantiation.

## Usage

```php
use Multilingual_Bridge\Helpers\WPML_User_Helper;
```

## Available Methods

### add_translation_manager_capability()

Add the `manage_translations` capability to a specific user, regardless of their current role.

```php
// Add capability to user ID 123
$result = WPML_User_Helper::add_translation_manager_capability(123);

if ($result) {
    echo "User now has translation manager capability";
} else {
    echo "Failed to add capability or user doesn't exist";
}

// Also works with WP_User object
$user = get_user_by('email', 'translator@example.com');
$result = WPML_User_Helper::add_translation_manager_capability($user);
```

**Key Features:**
- Checks if user already has the capability (returns `true` if already granted)
- Automatically synchronizes with WPML ATE managers
- Works with both user IDs and WP_User objects
- Returns `false` if user doesn't exist

### add_translation_manager_capability_to_role()

Add translation manager capability to all users assigned to a specific role.

```php
// Grant capability to all authors
$results = WPML_User_Helper::add_translation_manager_capability_to_role('author');

printf(
    'Successfully granted capability to %d out of %d users (%d failed)',
    $results['success'],
    $results['total'],
    $results['failed']
);

// Common use cases
$contributor_results = WPML_User_Helper::add_translation_manager_capability_to_role('contributor');
$custom_role_results = WPML_User_Helper::add_translation_manager_capability_to_role('content_manager');
```

**Return Format:**
```php
[
    'success' => 5,  // Number of users successfully updated
    'failed'  => 1,  // Number of failed operations
    'total'   => 6   // Total number of users processed
]
```

### has_translation_manager_capability()

Check if a user has the translation manager capability.

```php
// Check by user ID
if (WPML_User_Helper::has_translation_manager_capability(123)) {
    echo "User can manage translations";
}

// Check by WP_User object
$user = wp_get_current_user();
if (WPML_User_Helper::has_translation_manager_capability($user)) {
    // Show translation management interface
}

// Use in conditional logic
$users_with_translation_access = array_filter(
    get_users(['role' => 'author']),
    function($user) {
        return WPML_User_Helper::has_translation_manager_capability($user);
    }
);
```

### remove_translation_manager_capability()

Remove the translation manager capability from a specific user.

```php
// Remove capability from user ID 123
$result = WPML_User_Helper::remove_translation_manager_capability(123);

if ($result) {
    echo "Capability removed successfully";
}

// Also works with WP_User object
$user = get_user_by('login', 'former_translator');
WPML_User_Helper::remove_translation_manager_capability($user);
```

**Key Features:**
- Returns `true` if user doesn't have the capability (already removed)
- Automatically synchronizes with WPML ATE managers
- Works with both user IDs and WP_User objects

## Practical Examples

### Example 1: Setup Translation Team Without Editor Role

```php
// Grant translation access to content creators without full editor permissions
$content_team = ['author', 'contributor'];

foreach ($content_team as $role) {
    $results = WPML_User_Helper::add_translation_manager_capability_to_role($role);
    
    error_log(sprintf(
        'Role "%s": %d/%d users granted translation access',
        $role,
        $results['success'],
        $results['total']
    ));
}
```

### Example 2: Custom User Registration Hook

```php
// Automatically grant translation capability to new users with specific meta
add_action('user_register', function($user_id) {
    $user_meta = get_user_meta($user_id, 'is_translator', true);
    
    if ($user_meta === 'yes') {
        WPML_User_Helper::add_translation_manager_capability($user_id);
        
        // Log the action
        error_log("Translation capability granted to new user ID: $user_id");
    }
});
```

### Example 3: Admin Interface for Managing Translators

```php
// Add capability management to user profile page
add_action('show_user_profile', 'add_translation_capability_field');
add_action('edit_user_profile', 'add_translation_capability_field');

function add_translation_capability_field($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $has_capability = WPML_User_Helper::has_translation_manager_capability($user);
    ?>
    <h3>Translation Management</h3>
    <table class="form-table">
        <tr>
            <th><label for="translation_manager">Translation Manager</label></th>
            <td>
                <input type="checkbox" 
                       name="translation_manager" 
                       id="translation_manager" 
                       value="1" <?php checked($has_capability); ?> />
                <label for="translation_manager">
                    Grant translation management capability
                </label>
            </td>
        </tr>
    </table>
    <?php
}

// Save the capability setting
add_action('personal_options_update', 'save_translation_capability_field');
add_action('edit_user_profile_update', 'save_translation_capability_field');

function save_translation_capability_field($user_id) {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    $should_have_capability = isset($_POST['translation_manager']);
    $currently_has_capability = WPML_User_Helper::has_translation_manager_capability($user_id);
    
    if ($should_have_capability && !$currently_has_capability) {
        WPML_User_Helper::add_translation_manager_capability($user_id);
    } elseif (!$should_have_capability && $currently_has_capability) {
        WPML_User_Helper::remove_translation_manager_capability($user_id);
    }
}
```

### Example 4: Bulk User Management via WP-CLI

```php
// WP-CLI command to manage translation capabilities
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('translation-users', 'Translation_Users_CLI');
}

class Translation_Users_CLI {
    /**
     * Grant translation capability to users by role
     * 
     * ## OPTIONS
     * 
     * <role>
     * : The role to grant capability to
     * 
     * ## EXAMPLES
     * 
     *     wp translation-users grant-role author
     */
    public function grant_role($args) {
        $role = $args[0];
        $results = WPML_User_Helper::add_translation_manager_capability_to_role($role);
        
        WP_CLI::success(sprintf(
            'Granted translation capability to %d users (role: %s)',
            $results['success'],
            $role
        ));
        
        if ($results['failed'] > 0) {
            WP_CLI::warning(sprintf('%d operations failed', $results['failed']));
        }
    }
}
```

### Example 5: Integration with Custom User Roles

```php
// Create custom role with translation capability
add_action('init', function() {
    // Create a specialized translator role
    add_role('multilingual_editor', 'Multilingual Editor', [
        'read' => true,
        'edit_posts' => true,
        'edit_published_posts' => true,
    ]);
    
    // Grant translation capability to all users with this role
    WPML_User_Helper::add_translation_manager_capability_to_role('multilingual_editor');
});
```

## WPML Integration Details

### ATE Synchronization

The helper automatically calls `do_action('wpml_tm_ate_synchronize_managers', $user_id)` when capabilities are added or removed. This ensures:

- Users appear in WPML's translation manager lists
- Advanced Translation Editor permissions are properly synchronized
- Translation assignment workflows function correctly

### Capability vs Role Benefits

Using `manage_translations` capability instead of requiring editor role provides:

- **Granular Permissions**: Users get translation access without broader content editing rights
- **Role Flexibility**: Maintain existing role structures while adding translation capabilities
- **Security**: Limit user permissions to only what's needed for translation work
- **Scalability**: Easily manage translation teams across different user roles

## Error Handling

All methods handle invalid input gracefully:

- Invalid user IDs return `false` or appropriate default values
- Non-existent users are safely ignored
- Empty role names return empty result arrays
- Methods work with both user IDs and WP_User objects

## Performance Considerations

1. **Batch Operations**: When processing multiple users, use the role-based method instead of individual calls
2. **User Queries**: The role-based method uses `fields => 'ID'` for memory efficiency
3. **Capability Checks**: Methods check existing capabilities to avoid unnecessary operations

## Requirements

- WPML plugin must be installed and activated for full functionality
- PHP 8.0 or higher (for union type support)
- WordPress 5.0 or higher
- `manage_options` capability recommended for administrative functions

## Comparison with Direct WordPress Functions

| Direct WordPress | WPML_User_Helper |
|------------------|------------------|
| `$user->add_cap('manage_translations')` | `WPML_User_Helper::add_translation_manager_capability($user)` |
| Manual ATE synchronization | Automatic ATE synchronization included |
| No bulk role operations | `add_translation_manager_capability_to_role($role)` |
| Manual capability checking | `has_translation_manager_capability($user)` |
| No error handling | Graceful error handling for invalid users |

## Migration from Direct Capability Management

```php
// OLD: Manual capability management
$user = new WP_User($user_id);
if ($user && !$user->has_cap('manage_translations')) {
    $user->add_cap('manage_translations');
    do_action('wpml_tm_ate_synchronize_managers', $user_id);
}

// NEW: Using WPML_User_Helper
WPML_User_Helper::add_translation_manager_capability($user_id);
```