# Code Review Memory — translation-bundle

## Package Identity
- Namespace: `ChamberOrchestra\TranslationBundle` (PSR-4 from package root, no `src/`)
- Two systems: (1) entity localization via trait pairs, (2) form-field localization via UUID keys + XLIFF export

## Key Architectural Patterns
- `TranslatableTrait` + `TranslatableInterface` / `TranslationTrait` + `TranslationInterface` pair pattern
- `TranslateSubscriber` auto-maps ORM relationships at `loadClassMetadata` (priority -128)
- `TranslateSubscriber::setField()` uses Doctrine `ClassMetadata` reflection — cache the property to avoid per-load allocation
- `LocalizationLoaderChain` tagged with same tag it collects → circular dependency (self-reference); needs `exclude:` or non-implementing class
- `ExportTranslationCommand` hardcodes locale `'ru'` on lines 37 and 47; must use configured locales
- `TranslatableTrait::$translations` typed `iterable` but `findTranslationByLocale` calls `->get()` (Collection API); breaks on new (unsaved) entities where it's a plain `[]`
- `TranslatableProxyTrait::proxyCurrentLocaleTranslation()` calls private `getCurrentLocale()` from `TranslatableTrait` — works because traits share scope, but $translation can be null → fatal on call_user_func_array
- `TranslatableTypeExtension::$map` is an instance-level array; grows unbounded across all form renders in a request (memory leak for long-lived processes/workers)
- `uniqid()` used as form attribute key — not collision-safe under heavy load; prefer `spl_object_id($builder)` or `Uuid::v4()`
- `TranslatableTypeExtension::getContext()` declared with 1 param but called with 2 (`$event, $options`) — PHP silently ignores extra args, but signature is misleading
- `TranslationTrait::getTranslatableEntityClass()` strips 11 chars (`strlen('Translation')`) — fragile string arithmetic
- `TranslationHelper::parseId()` uses `\end()` on explode result of `@`-split: returns last segment which is the full `name.uuid` string, not just UUID — `getId()` will throw
- `DefaultLocalizationLoader::load()` return type is `string` (never null), but interface allows `null`; the chain's `null !== $value` short-circuit never fires for this loader → translator key-fallback always wins

## Common Issues Found
- Missing `\Doctrine\Common\Collections\ArrayCollection` init in `TranslatableTrait::$translations`
- Hardcoded locale in `ExportTranslationCommand` (both variable and sprintf format string)
- Self-tagging of `LocalizationLoaderChain` via `#[Autoconfigure]` on the interface
- Unchecked null return from `translate()` before `call_user_func_array` in ProxyTrait
- `file_put_contents` return not checked in export command
- No console output in export command

## Files Reviewed (Feb 2026)
- Utils/TranslationHelper.php
- Entity/TranslatableTrait.php, TranslationTrait.php, TranslatableProxyTrait.php
- EventSubscriber/TranslateSubscriber.php
- Form/Extension/TranslatableTypeExtension.php
- Form/Loader/LocalizationLoaderChain.php, LocalizationLoaderInterface.php, DefaultLocalizationLoader.php
- Cms/Form/Type/TranslationsType.php
- Command/ExportTranslationCommand.php
