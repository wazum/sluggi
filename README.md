# sluggi

Enhanced URL slug management for TYPO3 with a modern Lit-based web component.

## Features

- **Inline editing** - Edit slugs directly in the page form
- **Auto-sync** - Optionally keep slugs synchronized with the page title
- **Conflict detection** - Automatic detection and resolution of duplicate slugs
- **Last segment only** - Restrict editors to modifying only the last URL segment

## Installation

```bash
composer require wazum/sluggi
```

## Configuration

Extension settings in `Settings > Extension Configuration > sluggi`:

| Setting | Description |
|---------|-------------|
| `synchronize` | Enable the sync toggle feature |
| `last_segment_only` | Restrict non-admin users to editing only the last segment |

## Behavior

### Slashes in page titles

TYPO3 core's slug generator treats slashes in source fields (title, nav_title) as path separators, creating unintended URL segments. For example, a title "Products/Services" would generate `/products/services` (two segments) instead of `/products-services` (one segment).

**sluggi fixes this globally** by replacing slashes with the configured fallback character (default: `-`) before slug generation. This applies to:

- Manual slug editing
- Regenerate button clicks
- Auto-sync when title changes
- Page tree inline editing
