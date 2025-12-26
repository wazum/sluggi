# sluggi - Enhanced TYPO3 URL Slug Management

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

### Modern Slug Editor
Replaces the default slug field with a custom `<sluggi-element>` web component:
- Inline editing with immediate feedback
- Automatic slug proposals based on configured source fields (title, nav_title, etc.)
- Conflict detection with resolution options
- Visual indicators on source fields that influence slug generation

### Automatic Synchronization
When enabled, slugs automatically regenerate when source fields change:
- Toggle sync per-page via checkbox in the backend
- Child page slugs update when parent slugs change
- Redirects are created automatically (via EXT:redirects)

### Slug Locking
Protect important slugs from accidental changes:
- Lock individual page slugs
- Optionally protect descendant slugs when ancestor changes
- Locked slugs skip automatic regeneration

### Access Control
Restrict slug editing capabilities for non-admin users:
- **Last segment only**: Editors can only modify the final path segment
- **Hierarchy permissions**: Slug editing requires permission on the parent page
- **Full path editing toggle**: Allow permitted users to unlock full path editing via UI button

### Excluded Page Types
Configure page types (doktypes) that should not have slugs, such as Sysfolder (254), Recycler (255), or Spacer (199).

### Synchronization for other tables
Enable automatic slug synchronization for any table with a TCA slug field (e.g., `tx_news_domain_model_news`):
- Configure tables via `synchronize_tables` setting
- Slugs regenerate when any source field defined in `generatorOptions.fields` changes
- Supports multi-field slug generation (e.g., `['title', 'subtitle']` with `fieldSeparator`)

## Configuration

Configure via **Admin Tools > Settings > Extension Configuration > sluggi**:

| Setting | Description | Default |
|---------|-------------|---------|
| `synchronize` | Enable automatic slug regeneration when source fields change | On |
| `synchronize_tables` | Comma-separated list of tables for auto-sync (e.g., `tx_news_domain_model_news`) | Empty |
| `lock` | Enable slug locking feature | Off |
| `lock_descendants` | Protect child slugs when ancestor has locked slug | Off |
| `last_segment_only` | Non-admins can only edit the last URL segment | Off |
| `allow_full_path_editing` | Show toggle button for full path editing (with restrictions) | Off |
| `exclude_doktypes` | Comma-separated list of doktypes without slugs (e.g., `199,254,255`) | Empty |

## Field Access

Control which users can see and use _sluggi_ features via backend user/group permissions:

- `pages:slug` - Edit the slug field
- `pages:tx_sluggi_sync` - Toggle automatic synchronization
- `pages:slug_locked` - Lock/unlock slugs
- `pages:tx_sluggi_full_path` - Use full path editing toggle

## Upgrade Wizard

After enabling `exclude_doktypes`, run the upgrade wizard **Clear slugs for excluded page types** to remove existing slugs from those pages.

## Behavior Notes

### Slashes in page titles

TYPO3 core's slug generator treats slashes in source fields (title, nav_title) as path separators, creating unintended URL segments. For example, a title "Products/Services" would generate `/products/services` (two segments) instead of `/products-services` (one segment).

**_sluggi_ fixes this globally** by replacing slashes with the configured fallback character (default: `-`) before slug generation. This applies to:

- Manual slug editing
- Regenerate button clicks
- Auto-sync when title changes
- Page tree inline editing

## License

GPL-2.0-or-later
