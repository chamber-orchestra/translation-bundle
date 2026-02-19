[![PHP Composer](https://github.com/chamber-orchestra/translation-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/chamber-orchestra/translation-bundle/actions/workflows/php.yml)

# ChamberOrchestra Translation Bundle

A Symfony 8 bundle for multilingual applications. Provides two complementary i18n systems:

1. **Entity localization** — multi-locale Doctrine entity pairs with automatic ORM relationship mapping at runtime.
2. **Form field localization** — database-backed translation keys for individual form fields with XLIFF export.

## Features

- **Automatic ORM mapping** via `TranslateSubscriber`: maps `oneToMany`/`manyToOne` associations at runtime — no manual Doctrine mapping required.
- **Locale fallback chain** in `translate()`: requested locale → language fallback (`en_US` → `en`) → kernel default locale.
- **`TranslatableProxyTrait`** for transparent property delegation: `$post->title` reads from the current translation without extra calls.
- **Form field localization** via `localization: true` on `TextType`, `TextareaType`, and `WysiwygType` — stores opaque UUID-based keys in the entity, displays human-readable values in the form.
- **`LocalizationLoaderChain`** — tagged, prioritized loader chain for resolving existing translation values; extend with custom loaders.
- **`ExportTranslationCommand`** (`translation:export`) — writes un-exported `Translation` records to `+intl-icu.{locale}.xliff` files grouped by domain, then marks them as exported.
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
                'localization_domain' => 'messages',   // default: 'messages'
                'localization_context' => ['ui' => 'service_name'], // optional, passed to TranslationEvent
            ])
            ->add('description', TextareaType::class, [
                'localization' => true,
            ]);
    }
}
```

**What happens on submit:**

1. `TranslatableTypeExtension` dispatches a `TranslationEvent(key, value, context)`.
2. Your listener persists the `Translation` entity:

```php
use ChamberOrchestra\TranslationBundle\Events\TranslationEvent;
use ChamberOrchestra\TranslationBundle\Entity\Translation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class TranslationPersistListener
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(TranslationEvent $event): void
    {
        $translation = Translation::create($event->key, $event->value, $event->context);
        $this->em->persist($translation);
    }
}
```

3. The entity stores the key (`messages@name.{uuid}`), not the human-readable text. Symfony's translator resolves it at render time once exported.

**Export stored translations to XLIFF:**

```bash
php bin/console translation:export
```

Writes `{domain}+intl-icu.{locale}.xliff` files to `%translator.default_path%`, marks records as exported, and dispatches `TranslationExportedEvent`.

**Translation key format:**

```
{domain}@[prefix.]uuid
```

```php
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Symfony\Component\Uid\Uuid;

$uuid = Uuid::v7();
$key  = TranslationHelper::getLocalizationKey('messages', $uuid, 'service');
// → "messages@service.{uuid}"

TranslationHelper::getDomain($key);  // "messages"
TranslationHelper::getId($key);      // Uuid instance
TranslationHelper::getMessage($key); // "service.{uuid}"
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

Implement `LocalizationLoaderInterface` and tag the service with `chamber_orchestra.localization_loader`. The `LocalizationLoaderChain` resolves existing translations by priority:

```php
use ChamberOrchestra\TranslationBundle\Form\Loader\LocalizationLoaderInterface;

final class DatabaseLocalizationLoader implements LocalizationLoaderInterface
{
    public function load(string $key): ?string
    {
        // Return the human-readable value for this key, or null to pass through.
    }
}
```

```yaml
# config/services.yaml
App\Localization\DatabaseLocalizationLoader:
    tags:
        - { name: chamber_orchestra.localization_loader, priority: 10 }
```

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
