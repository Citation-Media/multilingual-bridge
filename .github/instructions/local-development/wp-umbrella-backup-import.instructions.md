---
applyTo: '**'
---
# WP Umbrella Backup Import

## Purpose
Guidelines for importing backups using WP Umbrella in local development environments.

## Steps
1. Download the backup archive from WP Umbrella.
2. Extract the archive to your local WordPress installation directory.
3. Import the database using the provided SQL file (usually via phpMyAdmin, WP-CLI, or Adminer).
4. Update the site URL and home options in the database to match your local environment.
5. Clear cache and permalinks.

## Notes
- Always verify file permissions after extraction.
- Never commit sensitive backup files to version control.
- For multisite, update all site URLs accordingly.
