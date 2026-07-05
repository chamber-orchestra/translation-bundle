[![PHP Composer](https://github.com/chamber-orchestra/translation-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/chamber-orchestra/translation-bundle/actions/workflows/php.yml)

# ChamberOrchestra Translation Bundle

A Symfony 8 bundle for multilingual applications. Provides two complementary i18n systems:

1. **Entity localization** — multi-locale Doctrine entity pairs with automatic ORM relationship mapping at runtime.
2. **Form field localization** — database-backed translation keys for individual form fields with YAML export.

## Features

- **Automatic ORM mapping** via `TranslateSubscriber`: maps `oneToMany`/`manyToOne` associations at runtime — no manual Doctrine mapping required.
- **Locale fallback chain** in `translate()`: requested locale → language fallback (`en_US` → `en`) → kernel default locale.
- **`TranslatableProxyTrait`** for transparent property delegation: `$post->title` reads from the current translation without extra calls.
- **Form field localization** via `localization: true` on `TextType`, `TextareaType`, and `WysiwygType` — stores opaque UUID-based keys in the entity, displays human-readable values in the form.
- **Built-in `TranslationEventSubscriber`** — automatically creates or updates `Translation` entities when a localized form field is submitted.
- **`LocalizationLoaderChain`** — tagged, prioritized loader chain for resolving translation values when rendering a localized form field; extend with custom loaders.
- **`ExportTranslationCommand`** (`translation:export`) — writes un-exported `Translation` records to `{domain}+intl-icu.{locale}.yaml` files grouped by domain, marks them as exported, and dispatches `TranslationExportedEvent`.
- **CMS integration** (optional, requires `chamber-orchestra/cms-bundle`) — `TranslationsType` collection pre-populated per locale, rendered as Bootstrap nav tabs.

## Requirements

- PHP ^8.5
- Symfony 8.0 (framework-bundle, form, translation, uid, console, http-foundation)
- Doctrine ORM ^3.0 + DoctrineBundle ^3.0

Optional:
- `chamber-orchestra/doctrine-clock-bundle` — required if translatable entities use `TimestampCreateTrait`
- `chamber-orchestra/cms-bundle` — CMS form integration (`TranslationsType`, `AbstractTranslatableDto`)

## Installation

```bash
composer require chamber-orchestra/translation-bundle
```

Enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    ChamberOrchestra\TranslationBundle\ChamberOrchestraTranslationBundle::class => ['all' => true],
];
```

## Usage

### System 1: Entity Localization

Define a translatable/translation entity pair. The `TranslateSubscriber` maps their Doctrine relationship automatically.

**Translatable entity** — implements `TranslatableInterface` + uses `TranslatableTrait`:

```php
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use ChamberOrchestra\TranslationBundle\Entity\TranslatableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Post implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    // No manual Doctrine mapping needed for $translations —
    // TranslateSubscriber wires it automatically at loadClassMetadata.
}
```

**Translation entity** — implements `TranslationInterface` + uses `TranslationTrait`.
The class name **must** be the translatable class name suffixed with `Translation`:

```php
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslationInterface;
use ChamberOrchestra\TranslationBundle\Entity\TranslationTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'post_translation')]
class PostTranslation implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    public string $title = '';

    // $locale and $translatable are provided by TranslationTrait.
    // The ManyToOne → Post association is mapped automatically.

    public function __construct(Post $post, string $locale, string $title)
    {
        $this->translatable = $post;
        $this->locale = $locale;
        $this->title = $title;
    }

    public function getId(): int { return $this->id; }
}
```

**Reading translations:**

```php
// Current request locale (injected by TranslateSubscriber on postLoad):
$post->translate()->title;

// Explicit locale:
$post->translate('ru')->title;

// Fallback chain: fr_CA → fr → kernel default locale:
$post->translate('fr_CA')->title;
```

**Template shorthand** with `TranslatableProxyTrait` — delegates `$post->title` to `$post->translate()->title`:

```php
use ChamberOrchestra\TranslationBundle\Entity\TranslatableProxyTrait;

class Post implements TranslatableInterface
{
    use TranslatableTrait;
    use TranslatableProxyTrait; // enables $post->title in Twig

    // ...
}
```

```twig
{# Both are equivalent after using TranslatableProxyTrait: #}
{{ post.translate().title }}
{{ post.title }}
```

**What `TranslateSubscriber` does automatically:**

| Trigger | Action |
|---------|--------|
| `loadClassMetadata` on `Post` | Maps `oneToMany` `translations` collection indexed by `locale`, cascade persist/remove |
| `loadClassMetadata` on `PostTranslation` | Maps `manyToOne` `translatable` with `CASCADE DELETE`; adds unique constraint `(translatable_id, locale)` |
| `postLoad` | Injects `currentLocale` and `defaultLocale` from `RequestStack` / kernel default |
| `prePersist` | Injects `currentLocale` and `defaultLocale` on new entities |

---

### System 2: Form Field Localization

Add `localization: true` to any `TextType`, `TextareaType`, or `WysiwygType` field. The entity stores an opaque key; the form shows the human-readable value.

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'localization' => true,
                'localization_domain' => 'entity',   // default: 'entity'
                'localization_context' => 'service', // optional
            ])
            ->add('description', TextareaType::class, [
                'localization' => true,
            ]);
    }
}
```

#### How it works

**On render (PRE_SET_DATA):**
- If the entity field is `null` (new record) — a new `entity@{uuid}` key is generated but not yet stored; if the user submits empty, the field stays `null` and nothing is persisted.
- If the entity field already has a key — `LocalizationLoaderChain` resolves it to a human-readable value for display.

**On submit (PRE_SUBMIT):**
- `TranslatableTypeExtension` dispatches `TranslationEvent(key, value, locale, context)`.
- The built-in `TranslationEventSubscriber` creates or updates the `Translation` entity in the database and marks it as needing export (`exported = false`).
- The entity field stores the opaque key, not the text.

#### Reading translations: two paths

| Consumer | Mechanism | Source |
|----------|-----------|--------|
| CMS form render | `LocalizationLoaderChain` | Configurable — see loaders below |
| Public site (`\|trans` Twig filter) | Symfony `Translator` | YAML catalog built by `cache:warmup` |

The public site uses the standard Twig `trans` filter:
```twig
{{ entity.name|trans([], 'entity') }}
```

This reads from the Symfony translation catalog (compiled from YAML), **not** from the database. A `translation:export` + `cache:warmup` cycle is required for changes to appear on the public site.

#### Exporting to YAML

```bash
php bin/console translation:export
```

Reads all `Translation` records with `exported = false`, writes them to:
```
{translations_path}/{domain}+intl-icu.{locale}.yaml
```
marks them as exported, and dispatches `TranslationExportedEvent`.

**Recommended deploy integration** — run export before `cache:warmup` so new values are compiled into the catalog immediately:

```bash
# In your deploy script, after stopping workers:
php bin/console translation:export
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
# Start workers — they boot with the fresh catalog.
```

#### TranslationExportedEvent

Dispatched after a successful export. Handle it in your application to perform any post-export actions (e.g., notifying external systems):

```php
use ChamberOrchestra\TranslationBundle\Events\TranslationExportedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(TranslationExportedEvent::class)]
class MyExportListener
{
    public function __invoke(TranslationExportedEvent $event): void
    {
        // e.g. notify a CDN, trigger a webhook, etc.
    }
}
```

#### Translation key format

```
{domain}@{uuid}
```

```php
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Symfony\Component\Uid\Uuid;

$key = TranslationHelper::getLocalizationKey('entity', Uuid::v7());
// → "entity@{uuid}"

TranslationHelper::getDomain($key);  // "entity"
TranslationHelper::getMessage($key); // "{uuid}"
TranslationHelper::parseId($key);    // "{uuid}" string
```

---

### CMS Integration (optional)

Requires `chamber-orchestra/cms-bundle`. Renders per-locale tabs in CMS edit forms:

```php
use ChamberOrchestra\TranslationBundle\Cms\Form\Type\TranslatableTypeTrait;

class PostType extends AbstractType
{
    use TranslatableTypeTrait; // adds $builder->add('translations', TranslationsType::class, ...)

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addTranslationsField($builder, PostTranslationType::class);
    }
}
```

Configure available locales in `config/services.yaml`:

```yaml
parameters:
    chamber_orchestra.translation_locales: [ru, en, de]
```

---

### Custom Localization Loaders

Implement `LocalizationLoaderInterface` and tag the service with `localization.loader`. The `LocalizationLoaderChain` tries loaders in descending priority order, returning the first non-`null` result.

**Default loader** (`DefaultLocalizationLoader`, priority 0) reads from the Symfony translator (YAML catalog). This means the CMS form shows the last exported value, not the latest saved value.

**Recommended: add a DB loader** (priority > 0) so CMS forms always show the current database value without requiring an export:

```php
use ChamberOrchestra\TranslationBundle\Contracts\Provider\LocaleProviderInterface;
use ChamberOrchestra\TranslationBundle\Form\Loader\LocalizationLoaderInterface;
use ChamberOrchestra\TranslationBundle\Repository\TranslationRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(tags: ['localization.loader'])]
#[AsTaggedItem(priority: 10)]
class DbLocalizationLoader implements LocalizationLoaderInterface
{
    public function __construct(
        private readonly TranslationRepository $repository,
        private readonly LocaleProviderInterface $localeProvider,
    ) {}

    public function load(string $key): ?string
    {
        $locale = $this->localeProvider->provideCurrentLocale()
            ?? $this->localeProvider->provideFallbackLocale()
            ?? 'en';

        return $this->repository->findOneByKey($key, $locale)?->getValue();
        // Returns null if not found → chain falls through to DefaultLocalizationLoader
    }
}
```

With this loader the CMS form reflects the saved value immediately after submit, while the public site only updates after export + `cache:warmup`.

---

## Testing

Integration tests require a PostgreSQL database. Set `DATABASE_URL` or use the default from `phpunit.xml.dist`:

```bash
composer install
DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/mydb?serverVersion=17&charset=utf8" \
    ./vendor/bin/phpunit
```

Run only unit tests (no database required):

```bash
./vendor/bin/phpunit --testsuite Unit
```

## License

MIT
