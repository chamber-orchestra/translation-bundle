# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Symfony bundle providing two complementary i18n systems:

1. **Entity localization** — multi-locale entity support via translatable/translation entity pairs, with ORM relationship auto-mapping at runtime.
2. **Form field localization** — database-backed translation keys for individual form fields (Text, Textarea, Wysiwyg), stored as `Translation` entities and exportable to XLIFF.

**Namespace:** `Dev\TranslationBundle` (PSR-4 from package root — no `src/` directory)
**Requirements:** PHP ^8.2, `dev/enum`

## Commands

```bash
composer install                        # Install dependencies
```

**Code style:** php-cs-fixer with `@PER-CS` + `@Symfony` rules, `declare(strict_types=1)` enforced. Config in `php-cs-fixer.dist.php`.

No test suite is configured yet.

## Architecture

### System 1: Entity Localization

Translatable entities use a pair pattern: `Post` + `PostTranslation`, connected automatically by `TranslateSubscriber`.

**Entity setup:**
- The translatable entity implements `Contracts\Entity\TranslatableInterface` + uses `Entity\TranslatableTrait`
- The translation entity implements `Contracts\Entity\TranslationInterface` + uses `Entity\TranslationTrait`
- `TranslatableTrait::getTranslationEntityClass()` resolves the translation class by appending `Translation` to the owning class name

**ORM auto-mapping (TranslateSubscriber):**
- Listens on `loadClassMetadata` (priority -128)
- Detects `TranslatableInterface` implementors → maps `oneToMany` `translations` collection indexed by locale (`indexBy: locale`)
- Detects `TranslationInterface` implementors → maps `manyToOne` `translatable` with `CASCADE DELETE` + adds a unique constraint on `(translatable_id, locale)`
- Listens on `postLoad`/`prePersist` → injects `currentLocale` and `defaultLocale` into loaded entities via reflection

**Locale resolution (`TranslatableTrait::translate()`):**
1. Requested locale → exact match in `translations` collection
2. Language fallback: `en_US` → `en`
3. `defaultLocale` (kernel default)

**Locale provider (`Provider\LocaleProvider`):**
- Reads current locale from `RequestStack` if a request is active
- Falls back to `translator.locale` → `kernel.default_locale`

**Template access (`Entity\TranslatableProxyTrait`):** Delegates property access on the owning entity to its current translation (e.g., `$post->title` → `$post->translate()->getTitle()`).

### System 2: Form Field Localization

Form fields (TextType, TextareaType, WysiwygType) can opt-in via `localization: true` option.

**Flow on render:**
1. `TranslatableTypeExtension::PRE_SET_DATA` — if existing data is a translation key, load translated value via `LocalizationLoaderChain`; otherwise generate a new UUID-based key (`domain@name.uuid`)
2. Field value displayed is the human-readable translation; the stored form value is the opaque key

**Flow on submit:**
1. `TranslatableTypeExtension::PRE_SUBMIT` — dispatches `TranslationEvent(key, submittedValue, context)` and stores the key back as the field value (entity stores the key, not the text)
2. A listener (outside this bundle) should handle `TranslationEvent` to persist the `Translation` entity

**Translation entity (`Entity\Translation`):** Stores `domain`, `message` (UUID part of key), `value`, `context`, and `isExported` flag.

**Export command (`Command\ExportTranslationCommand`):** Reads un-exported `Translation` records, writes them to XLIFF files (`+intl-icu.{locale}.xliff`) grouped by domain under `%translator.default_path%`, marks them as exported, and dispatches `TranslationExportedEvent`.

**Loader chain (`Form\Loader\LocalizationLoaderChain`):** Tagged services implementing `LocalizationLoaderInterface` with priority. Default loader uses Symfony's translator service.

### CMS Form Integration (optional, requires `dev/cms-bundle`)

- `Cms\Form\Type\TranslationsType` — collection type that pre-populates one entry per configured locale; used in CMS edit forms for translatable entities
- `Cms\Form\Dto\AbstractTranslatableDto` / `AbstractTranslationDto` — base DTOs for handling multi-locale form data
- `Cms\Form\Type\TranslatableTypeTrait` — adds `translations` field to a CMS form using `TranslationsType`
- Twig views in `Resources/views/cms/form/` render translations as Bootstrap nav tabs (one tab per locale)

### Configuration

Default locales in `Resources/config/services.yaml`:
```yaml
parameters:
  dev.translation_locales: [ru, en]
```

Override in the consuming application's service config to change available locales.

## Key Files

| File | Role |
|------|------|
| `EventSubscriber/TranslateSubscriber.php` | Core ORM auto-mapping + locale injection |
| `Entity/TranslatableTrait.php` | Multi-locale `translate()` with fallback logic |
| `Form/Extension/TranslatableTypeExtension.php` | Key-based form field localization |
| `Form/Loader/LocalizationLoaderChain.php` | Pluggable loader for existing translation values |
| `Command/ExportTranslationCommand.php` | XLIFF export of DB-stored translations |
| `Utils/TranslationHelper.php` | Parse/build translation keys (`domain@name.uuid`) |

## Code Conventions

- PSR-12, `declare(strict_types=1)`, 4-space indent
- Typed properties and return types; favor `readonly`
- Constructor injection; autowiring and autoconfiguration throughout
- Commit style: short, action-oriented with optional bracketed scope — `[fix] ...`, `[master] ...`
