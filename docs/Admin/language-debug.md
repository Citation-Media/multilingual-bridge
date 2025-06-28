# Language Debug Tool

The Language Debug tool helps administrators manage posts that are in languages no longer configured in WPML.

## Overview

When languages are removed or deactivated from WPML, posts in those languages can become orphaned. The Language Debug tool provides functionality to:

- Find posts in unconfigured languages
- Delete posts in unconfigured languages
- Reassign posts to active languages

## Location

The Language Debug tool can be found in the WordPress admin under:
**Tools → Language Debug**

## Features

### 1. Preflight Check
Performs a dry run to show how many posts would be affected without making any changes.

### 2. Delete Posts
Permanently deletes all posts that are in languages not currently active in WPML.

### 3. Fix Language Assignment
Reassigns posts from unconfigured languages to a selected active language.

## Usage

1. Navigate to **Tools → Language Debug**
2. Select an action:
   - **Preflight check**: See what would be affected
   - **Delete posts**: Remove posts in unconfigured languages
   - **Fix language**: Reassign posts to an active language
3. Choose post type(s) to process (or select "All post types")
4. If fixing languages, select the target language
5. Click "Execute Debug Action"

## Technical Details

The Language Debug functionality is implemented in:
- `src/Admin/Language_Debug.php` - Admin interface and processing logic
- `src/Helpers/WPML_Post_Helper.php` - Helper methods for WPML operations

### Key Methods in WPML_Post_Helper

- `is_post_in_unconfigured_language()` - Checks if a post is in a language not active in WPML
- `get_language()` - Gets the language code of a post
- `get_active_language_codes()` - Returns all active WPML language codes

## Security

- Requires `manage_options` capability
- All actions are protected with nonce verification
- Destructive actions (delete/fix) show confirmation dialogs