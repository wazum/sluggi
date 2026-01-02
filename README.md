# sluggi - Enhanced TYPO3 URL Path Management

[![Tests](https://github.com/wazum/sluggi/workflows/Tests/badge.svg)](https://github.com/wazum/sluggi/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%20|%208.3%20|%208.4-blue.svg)](https://www.php.net/)
[![TYPO3](https://img.shields.io/badge/TYPO3-12.4%20|%2013.4%20|%2014-orange.svg)](https://typo3.org/)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)

A TYPO3 extension that replaces the core slug field with an improved interface featuring inline editing, automatic synchronization, conflict detection, and fine-grained access control.

## Requirements

- TYPO3 12.4 - 14.x
- PHP 8.2+
- EXT:redirects

## Installation

```bash
composer require wazum/sluggi
```

## Features

### Modern URL Path Editor
Replaces the default slug field with a custom `<sluggi-element>` web component:
- Inline editing with immediate feedback
- Automatic URL path proposals based on configured source fields (title, nav_title, etc.)
- Conflict detection with resolution options
- Visual indicators on source fields that influence URL path generation
- Copy page URL to clipboard button (optional)
- Collapsed controls mode for compact UI display (optional)

### Automatic Synchronization
When enabled, URL paths automatically regenerate when source fields change:
- Toggle sync per-page via checkbox in the backend
- Child page URL paths update when parent paths change
- Redirects are created automatically (via EXT:redirects)

### URL Path Locking
Protect important URL paths from accidental changes:
- Lock individual page URL paths
- Optionally protect descendant paths when ancestor changes
- Locked URL paths skip automatic regeneration
- Auto-lock when full path is manually edited

### Access Control
Restrict URL path editing capabilities for non-admin users:
- **Last segment only**: Editors can only modify the final path segment
- **Hierarchy permissions**: URL path editing is restricted based on page edit permissions in the rootline
- **Full path editing button**: Allow permitted users to directly enter full path edit mode via a dedicated button

### Excluded Page Types
Configure page types (doktypes) that should not have URL paths, such as Sysfolder (254), Recycler (255), or Spacer (199).

### Synchronization for Other Tables
Enable automatic URL path synchronization for any table with a TCA slug field (e.g., `tx_news_domain_model_news`):
- Configure tables via `synchronize_tables` setting
- URL paths regenerate when any source field defined in `generatorOptions.fields` changes
- Supports multi-field generation (e.g., `['title', 'subtitle']` with `fieldSeparator`)

### Duplicate Slug Prevention
Automatically prevents duplicate slugs in scenarios where TYPO3 core doesn't:
- **Page/record copy**: Copied pages get unique slugs based on target location
- **Page move**: Child page slugs update to reflect new parent path
- **Recycler restore**: Restored records get unique slugs if conflicts exist

## Configuration

Configure via **Admin Tools > Settings > Extension Configuration > sluggi**:

| Setting | Description | Default |
|---------|-------------|---------|
| `synchronize` | Enable automatic URL path regeneration when source fields change | On |
| `synchronize_default` | Enable sync by default for new pages | On |
| `synchronize_tables` | Comma-separated list of tables for auto-sync (e.g., `tx_news_domain_model_news`) | Empty |
| `lock` | Enable URL path locking feature | Off |
| `lock_descendants` | Protect child paths when ancestor has locked URL path | Off |
| `last_segment_only` | Non-admins can only edit the last URL segment | Off |
| `allow_full_path_editing` | Show full path edit button (requires `last_segment_only`) | Off |
| `exclude_doktypes` | Comma-separated list of doktypes without URL paths (e.g., `199,254,255`) | Empty |
| `copy_url` | Show button to copy full page URL to clipboard | Off |
| `preserve_underscore` | Keep underscores in URL paths instead of replacing with dashes (RFC 3986 compliant) | Off |

## User Settings

Individual users can configure their preferences via **User Settings > Personalization**:

| Setting | Description | Default |
|---------|-------------|---------|
| `Sluggi: Use compact controls menu` | Hide controls behind a menu icon, expand on hover | Off |

## Field Access

Control which users can see and use _sluggi_ features via backend user/group permissions:

- `pages:slug` - Edit the URL path field
- `pages:tx_sluggi_sync` - Toggle automatic synchronization
- `pages:slug_locked` - Lock/unlock URL paths
- `pages:tx_sluggi_full_path` - Use full path editing button

## Upgrade Wizard

After enabling `exclude_doktypes`, run the upgrade wizard **Clear slugs for excluded page types** to remove existing URL paths from those pages.

## Behavior Notes

### Slashes in Page Titles

TYPO3 core's slug generator treats slashes in source fields (title, nav_title) as path separators, creating unintended URL segments. For example, a title "Products/Services" would generate `/products/services` (two segments) instead of `/products-services` (one segment).

**_sluggi_ fixes this globally** by replacing slashes with the configured fallback character (default: `-`) before URL path generation. This applies to:

- Manual URL path editing
- Regenerate button clicks
- Auto-sync when title changes
- Page tree inline editing

### Self-Referencing Redirects

_sluggi_ automatically prevents EXT:redirects from creating redirects that point to themselves.

### Recycler Restore Protection

When restoring deleted pages or records from the recycler, TYPO3 core does not check for slug conflicts. This can result in duplicate slugs causing 500 errors or routing issues.

**_sluggi_ automatically validates and regenerates slugs** when records are restored:

- **Pages**: Always validated, using site-based uniqueness
- **Other tables**: Validated when configured in `synchronize_tables`, respecting the TCA `eval` setting (`unique`, `uniqueInPid`, or `uniqueInSite`)

If a conflict is detected, sluggi appends a numeric suffix (e.g., `/my-page` becomes `/my-page-1`).

## Suggested Extensions

- [news-redirect-slug-change](https://github.com/georgringer/news-redirect-slug-change) - Generate redirects when news slugs change
- [ig-slug](https://github.com/internetgalerie/ig-slug) - Rebuild URL slugs in bulk

## License

GPL-2.0-or-later
