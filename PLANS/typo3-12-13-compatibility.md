# Sluggi Extension - TYPO3 12/13 Compatibility Plan

## Summary

Make the `local/sluggi` extension compatible with TYPO3 12.4+ and 13.x while maintaining 14.x support. The current implementation is TYPO3 14-specific. 

**Strategy:** Single compatibility class with centralized version-specific values using `match` expressions. Minimal changes to production code - easy to remove when dropping version support.

```
┌─────────────────────────────────────────────────────────────────┐
│  Production Code (6 lines changed)                              │
├─────────────────────────────────────────────────────────────────┤
│  SlugElement.php (4 changes)                                    │
│    → Remove 'readonly' keyword                                  │
│    → Typo3Compatibility::getJavaScriptModulesKey()              │
│    → Typo3Compatibility::getFormWizardsElementClass()           │
│    → Typo3Compatibility::getLegendClass()                       │
│                                                                  │
│  FormSlugAjaxController.php (1 change)                          │
│    → Remove 'readonly' keyword                                  │
│                                                                  │
│  PostModifierTest.php (1 change)                                │
│    → Typo3Compatibility::hasTcaSchemaFactory()                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  Compatibility Layer (1 file, ~100 lines)                       │
├─────────────────────────────────────────────────────────────────┤
│  Classes/Compatibility/Typo3Compatibility.php                   │
│                                                                  │
│  All version-specific logic in match expressions:               │
│    • getJavaScriptModulesKey()                                  │
│    • getFormWizardsElementClass()                               │
│    • getLegendClass()                                           │
│    • canExtendAsReadonly() (for documentation only)             │
│    • hasTcaSchemaFactory()                                      │
│                                                                  │
│  To drop v12: Delete this file + revert 6 lines                 │
└─────────────────────────────────────────────────────────────────┘
```

## Key Findings

### Backend Compatibility (PHP)

All version differences centralized in `Typo3Compatibility` class using `match` expressions:

| Difference | TYPO3 12 | TYPO3 13 | TYPO3 14 | Method |
|------------|----------|----------|----------|--------|
| **JS module result key** | `requireJsModules` | `javaScriptModules` | `javaScriptModules` | `getJavaScriptModulesKey()` |
| **FormWizards CSS class** | `form-wizards-element` | `form-wizards-item-element` | `form-wizards-item-element` | `getFormWizardsElementClass()` |
| **Legend CSS class** | `form-legend` | `form-label` | `form-label` | `getLegendClass()` |
| **Parent class readonly** | ❌ Not readonly | ❌ Not readonly | ✅ `readonly` | `canExtendAsReadonly()` |
| **TcaSchemaFactory** | ❌ N/A | ✅ Available (13.2+) | ✅ Available | `hasTcaSchemaFactory()` |

### Frontend Compatibility (TypeScript/CSS)

**No changes needed!** All frontend code is version-agnostic:

| Component | Compatibility | Reason |
|-----------|---------------|--------|
| ES6 module imports (`@typo3/*`) | ✅ TYPO3 12+ | Import maps supported |
| TypeScript component | ✅ All versions | Uses custom selectors only |
| DOM selectors (`.sluggi-*`) | ✅ Version-agnostic | Custom classes, not core classes |
| CSS custom properties | ✅ All versions | No version-specific CSS |
| `light-dark()` CSS function | ⚠️ Optional fallback | Add `@supports` for older browsers (TYPO3 12 backend) |

---

## Implementation Steps

### 1. Update Version Constraints

**File: `composer.json`**
```json
{
    "require": {
        "php": "^8.2",
        "typo3/cms-core": "^12.4 || ^13.0 || ^14.0",
        "typo3/cms-backend": "^12.4 || ^13.0 || ^14.0",
        "typo3/cms-redirects": "^12.4 || ^13.0 || ^14.0"
    }
}
```

**File: `ext_emconf.php`** - Already correct (12.4.0-14.99.99)

---

### 2. Create Centralized Compatibility Layer

**New file: `Classes/Compatibility/Typo3Compatibility.php`**

All version-specific differences in **one place** using `match` expressions:

```php
<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Compatibility;

use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Centralized compatibility layer for TYPO3 12/13/14.
 * All version-specific values are defined here using match expressions.
 * 
 * @deprecated Remove this entire file when dropping TYPO3 12 support
 */
final readonly class Typo3Compatibility
{
    private static ?int $majorVersion = null;

    public static function getMajorVersion(): int
    {
        if (self::$majorVersion === null) {
            self::$majorVersion = (new Typo3Version())->getMajorVersion();
        }
        return self::$majorVersion;
    }

    /**
     * FormEngine JavaScript module result array key
     * TYPO3 12: 'requireJsModules'
     * TYPO3 13+: 'javaScriptModules'
     */
    public static function getJavaScriptModulesKey(): string
    {
        return match (self::getMajorVersion()) {
            12 => 'requireJsModules',
            default => 'javaScriptModules',
        };
    }

    /**
     * FormEngine form wizards element CSS class
     * TYPO3 12: 'form-wizards-element'
     * TYPO3 13+: 'form-wizards-item-element'
     */
    public static function getFormWizardsElementClass(): string
    {
        return match (self::getMajorVersion()) {
            12 => 'form-wizards-element',
            default => 'form-wizards-item-element',
        };
    }

    /**
     * Legend CSS class for fieldset labels
     * TYPO3 12: 'form-legend'
     * TYPO3 13+: 'form-label'
     */
    public static function getLegendClass(): string
    {
        return match (self::getMajorVersion()) {
            12 => 'form-legend t3js-formengine-legend',
            default => 'form-label t3js-formengine-label',
        };
    }

    /**
     * Check if we can extend classes as readonly
     * TYPO3 12/13: Parent classes are NOT readonly
     * TYPO3 14: Parent classes ARE readonly
     * 
     * Affects:
     * - FormSlugAjaxController (extends AbstractFormEngineAjaxController)
     * - SlugElement (extends AbstractFormElement)
     */
    public static function canExtendAsReadonly(): bool
    {
        return self::getMajorVersion() >= 14;
    }

    /**
     * Check if TcaSchemaFactory exists (for tests)
     * TYPO3 12: false
     * TYPO3 13.0-13.1: false
     * TYPO3 13.2+: true
     */
    public static function hasTcaSchemaFactory(): bool
    {
        return class_exists(\TYPO3\CMS\Core\Schema\TcaSchemaFactory::class);
    }
}
```

---

### 3. Update SlugElement (3 lines changed)

**File: `Classes/Form/Element/SlugElement.php`**

```php
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

// Change 1: Line 21 - Remove readonly when extending non-readonly parent
// Before:
final class SlugElement extends AbstractFormElement

// After:
// @deprecated TYPO3 12/13 compatibility - add 'readonly' when dropping v12/v13
final class SlugElement extends AbstractFormElement

// Change 2: Line 86 - Dynamic JS module key
// Before:
$resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@wazum/sluggi/sluggi-element.js');

// After:
// @deprecated TYPO3 12 compatibility - use 'javaScriptModules' when dropping v12
$resultArray[Typo3Compatibility::getJavaScriptModulesKey()][] = JavaScriptModuleInstruction::create('@wazum/sluggi/sluggi-element.js');

// Change 3: Line 215 - Dynamic CSS class in buildSlugElementHtml()
// Before:
'<div class="form-wizards-item-element">'

// After:
// @deprecated TYPO3 12 compatibility - use 'form-wizards-item-element' when dropping v12
'<div class="' . Typo3Compatibility::getFormWizardsElementClass() . '">'

// Change 4: Line 341 - Dynamic legend CSS class in wrapWithFieldsetAndLegend()
// Before:
'<legend class="form-label t3js-formengine-label">'

// After:
// @deprecated TYPO3 12 compatibility - use 'form-label t3js-formengine-label' when dropping v12
'<legend class="' . Typo3Compatibility::getLegendClass() . '">'
```

---

### 4. Update FormSlugAjaxController (1 line changed)

**File: `Classes/Controller/FormSlugAjaxController.php`**

```php
// Change: Line 14 - Remove readonly when extending non-readonly parent
// Before:
final readonly class FormSlugAjaxController extends CoreFormSlugAjaxController

// After:
// @deprecated TYPO3 12/13 compatibility - add 'readonly' when dropping v12/v13
final class FormSlugAjaxController extends CoreFormSlugAjaxController
```

---

### 5. Update Functional Test (1 line changed)

**File: `Tests/Functional/DataHandler/PostModifierTest.php`**

```php
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

// Line 71 - Conditional TcaSchemaFactory
// Before:
GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);

// After:
// @deprecated TYPO3 12/13.0-13.1 compatibility - always call when requiring TYPO3 13.2+
if (Typo3Compatibility::hasTcaSchemaFactory()) {
    GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);
}
```

---

### 6. Update CI/CD Matrix

**File: `.github/workflows/tests.yml`** (or similar)

We need to ensure all supported combinations are tested.

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    name: PHP ${{ matrix.php }} - TYPO3 ${{ matrix.typo3 }}
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        include:
          # TYPO3 12.4 LTS (PHP 8.1 - 8.3)
          - php: '8.2'
            typo3: '12.4'
          - php: '8.3'
            typo3: '12.4'
            
          # TYPO3 13.4 LTS (PHP 8.2 - 8.4)
          - php: '8.2'
            typo3: '13.4'
          - php: '8.3'
            typo3: '13.4'
          - php: '8.4'
            typo3: '13.4'
            
          # TYPO3 14.x (PHP 8.2 - 8.4)
          - php: '8.2'
            typo3: '14.0'
          - php: '8.3'
            typo3: '14.0'
          - php: '8.4'
            typo3: '14.0'

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: intl, mbstring, json, zip, pdo_sqlite

      - name: Install dependencies
        run: composer require "typo3/cms-core:^${{ matrix.typo3 }}" --no-update

      - name: Composer install
        run: composer update --prefer-dist --no-progress

      - name: Run Unit Tests
        run: composer test:unit

      - name: Run Functional Tests
        run: composer test:functional
```

---

### 8. Frontend CSS Fallbacks (Optional)

**File: `src/styles/sluggi-element.scss`**

Only if supporting older browsers in TYPO3 12 backend:

```scss
:host {
    // Fallback for browsers without light-dark() support
    --sluggi-bg: #fff;
    --sluggi-color: #212529;
    --sluggi-border-color: #ced4da;

    // @deprecated TYPO3 12 - remove @supports when dropping v12
    @supports (color: light-dark(#000, #fff)) {
        --sluggi-bg: var(--typo3-input-bg, light-dark(#fff, #212529));
        --sluggi-color: var(--typo3-input-color, light-dark(#212529, #dee2e6));
        --sluggi-border-color: var(--typo3-input-border-color, light-dark(#ced4da, #495057));
    }
}
```

**Note:** Modern browsers in TYPO3 13/14 backend support `light-dark()` natively. This is only needed for TYPO3 12 compatibility.

---

### 6. Update CI/CD Matrix (If Applicable)

Add TYPO3 12/13 to test matrix:

```yaml
matrix:
  php: ['8.2', '8.3', '8.4']
  typo3: ['12.4', '13.4', '14.0']
  exclude:
    - php: '8.4'
      typo3: '12.4'  # TYPO3 12 doesn't support PHP 8.4
```

---

## Additional Version Differences (No Action Needed)

These differences exist between TYPO3 versions but **do not affect sluggi**:

### Backend (PHP)
| Component | TYPO3 12 | TYPO3 13/14 | Why N/A |
|-----------|----------|-------------|---------|
| Icon size | `Icon::SIZE_SMALL` | `IconSize::SMALL` enum | Sluggi doesn't use IconFactory |
| HMAC | `GeneralUtility::hmac()` | `HashService->hmac()` | Already using HashService ✅ |

### Frontend (HTML/CSS rendered by core)
| Component | TYPO3 12 | TYPO3 13/14 | Why N/A |
|-----------|----------|-------------|---------|
| Bootstrap prefix | `input-group-addon` | `input-group-text` | Custom `<sluggi-element>`, not using core HTML |
| Callout structure | `.callout-body` | `.callout-content > .callout-body` | Core renders callouts, not sluggi |

### API Stability (No changes needed)
| API | Status |
|-----|--------|
| `RecordStateFactory` | ✅ Stable since TYPO3 10 |
| `JavaScriptModuleInstruction` | ✅ Available since TYPO3 12.0 |
| DataHandler correlation ID | ✅ Available since TYPO3 10.1 |
| `SlugRedirectChangeItemFactory` | ✅ Available since TYPO3 12 |
| `Configuration/JavaScriptModules.php` | ✅ Supported since TYPO3 12.0 |
| `ModifyAutoCreateRedirectRecordBeforePersistingEvent` | ✅ Available since TYPO3 12.3 (we require 12.4) |

---

## Files to Modify (Summary)

| File | Lines Changed | Type |
|------|---------------|------|
| `composer.json` | 3 | Version constraints |
| `Classes/Compatibility/Typo3Compatibility.php` | **NEW** (~100 lines) | Compatibility layer |
| `Classes/Form/Element/SlugElement.php` | 4 | Remove `readonly`, use compatibility layer |
| `Classes/Controller/FormSlugAjaxController.php` | 1 | Remove `readonly` |
| `Tests/Functional/DataHandler/PostModifierTest.php` | 1 | Use compatibility layer |
| `.github/workflows/tests.yml` | ~20 | Update test matrix |
| `src/styles/sluggi-element.scss` | ~10 (optional) | CSS fallbacks |

**Total production code changes: 6 lines** (SlugElement + FormSlugAjaxController + PostModifierTest)

---

## Testing Strategy

After implementing compatibility changes, test on all supported versions:

### Automated Testing Matrix (GitHub Actions)

We run tests across all supported PHP and TYPO3 combinations to ensure full compatibility.

| TYPO3 Version | PHP 8.2 | PHP 8.3 | PHP 8.4 |
|---------------|---------|---------|---------|
| **12.4 LTS**  | ✅      | ✅      | ❌ (Not supported) |
| **13.4 LTS**  | ✅      | ✅      | ✅      |
| **14.x**      | ✅      | ✅      | ✅      |

### Manual Testing Matrix
| TYPO3 Version | PHP Version | Test Focus |
|---------------|-------------|------------|
| 12.4 LTS | 8.2, 8.3 | FormEngine rendering, JS module loading, CSS classes |
| 13.4 LTS | 8.2, 8.3, 8.4 | JavaScript loading, callout display, icon rendering |
| 14.x | 8.2, 8.3, 8.4 | Full feature set, E2E tests |

### Key Test Scenarios
1. **FormEngine Rendering:** Slug field displays correctly in page edit form
2. **JavaScript Loading:** `<sluggi-element>` initializes without console errors
3. **Inline Editing:** Click to edit, sync toggle, regenerate button
4. **Conflict Detection:** Test slug uniqueness validation
5. **Source Field Badges:** Verify badges appear on title/nav_title fields
6. **DataHandler Hooks:** Auto-sync, page move, page copy operations

---

## Removal Strategy (Dropping TYPO3 12)

**Step 1: Delete compatibility layer**
```bash
rm Classes/Compatibility/Typo3Compatibility.php
```

**Step 2: Revert production code (6 lines)**

File: `Classes/Form/Element/SlugElement.php`
```php
// Line 21: Add readonly
- final class SlugElement extends AbstractFormElement
+ final readonly class SlugElement extends AbstractFormElement

// Line 86: Remove compatibility call
- $resultArray[Typo3Compatibility::getJavaScriptModulesKey()][] = ...
+ $resultArray['javaScriptModules'][] = ...

// Line 215: Remove compatibility call
- '<div class="' . Typo3Compatibility::getFormWizardsElementClass() . '">'
+ '<div class="form-wizards-item-element">'

// Line 341: Remove compatibility call
- '<legend class="' . Typo3Compatibility::getLegendClass() . '">'
+ '<legend class="form-label t3js-formengine-label">'
```

File: `Classes/Controller/FormSlugAjaxController.php`
```php
// Line 14: Add readonly
- final class FormSlugAjaxController extends CoreFormSlugAjaxController
+ final readonly class FormSlugAjaxController extends CoreFormSlugAjaxController
```

File: `Tests/Functional/DataHandler/PostModifierTest.php`
```php
// Line 71: Remove conditional
- if (Typo3Compatibility::hasTcaSchemaFactory()) {
      GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);
- }
+ GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);
```

**Step 3: Update constraints**
```json
// composer.json
"typo3/cms-core": "^13.0 || ^14.0"
```

**Step 4: Verify (automated)**
```bash
# Search for any remaining compatibility references
grep -r "Typo3Compatibility" Classes/ Tests/ src/
grep -r "@deprecated TYPO3 12" Classes/ Tests/ src/

# Should return no results
```

**Done!** All compatibility code removed in 4 simple steps.

---

## No Changes Required

These files work as-is across TYPO3 12-14:
- `Configuration/JavaScriptModules.php`
- `Configuration/Services.yaml` (event exists in 12.4+)
- `src/components/sluggi-element.ts` (uses custom selectors)
- `src/styles/sluggi-element.scss` (except optional `@supports` fallback)
- `vite.config.ts`
- All DataHandler classes
- All Service classes
- `ext_localconf.php`

---

## Quick Reference

### Adding New Version-Specific Code

When you need a new version-specific value:

1. Add a `match` method to `Classes/Compatibility/Typo3Compatibility.php`
2. Use it in production code: `Typo3Compatibility::getMethodName()`
3. Add `@deprecated TYPO3 12` comment

**Example:**
```php
// In Typo3Compatibility.php
public static function getSomeValue(): string
{
    return match (self::getMajorVersion()) {
        12 => 'value-for-v12',
        13 => 'value-for-v13', 
        default => 'value-for-v14+',
    };
}

// In production code
$value = Typo3Compatibility::getSomeValue(); // @deprecated TYPO3 12
```

### Checking Current Compatibility Surface

```bash
# All compatibility code is in one file:
cat Classes/Compatibility/Typo3Compatibility.php

```bash
# Find all usages in production:
grep -r "Typo3Compatibility::" Classes/ Tests/

# Should only find 4 locations:
# - SlugElement.php (3 calls)
# - PostModifierTest.php (1 call)

# Check for readonly classes that need updating:
grep -rn "final readonly class.*extends" Classes/
```
```
