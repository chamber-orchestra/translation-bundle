<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslationInterface;
use ChamberOrchestra\TranslationBundle\Entity\TranslatableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Minimal translation stub for unit testing.
 */
final class StubTranslation implements TranslationInterface
{
    public function __construct(private readonly string $locale)
    {
    }

    public static function getTranslatableEntityClass(): string
    {
        return StubTranslatable::class;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}

/**
 * Minimal translatable stub for unit testing.
 */
final class StubTranslatable implements TranslatableInterface
{
    use TranslatableTrait;

    public static function getTranslationEntityClass(): string
    {
        return StubTranslation::class;
    }

    /** Expose protected fields for test assertions. */
    public function setCurrentLocalePublic(string $locale): void
    {
        $this->currentLocale = $locale;
    }

    public function setDefaultLocalePublic(string $locale): void
    {
        $this->defaultLocale = $locale;
    }
}

final class TranslatableTraitTest extends TestCase
{
    private StubTranslatable $entity;

    protected function setUp(): void
    {
        $this->entity = new StubTranslatable();
    }

    #[Test]
    public function getTranslationsReturnsArrayCollectionForNewEntity(): void
    {
        $col = $this->entity->getTranslations();

        self::assertInstanceOf(ArrayCollection::class, $col);
        self::assertCount(0, $col);
    }

    #[Test]
    public function getTranslationsReturnsSameInstanceOnMultipleCalls(): void
    {
        self::assertSame($this->entity->getTranslations(), $this->entity->getTranslations());
    }

    #[Test]
    public function translateReturnsExactMatchForCurrentLocale(): void
    {
        $en = new StubTranslation('en');
        $this->entity->getTranslations()->set('en', $en);
        $this->entity->setCurrentLocalePublic('en');

        self::assertSame($en, $this->entity->translate());
    }

    #[Test]
    public function translateReturnsExplicitLocale(): void
    {
        $fr = new StubTranslation('fr');
        $this->entity->getTranslations()->set('fr', $fr);

        self::assertSame($fr, $this->entity->translate('fr'));
    }

    #[Test]
    public function translateFallsBackToLanguageCode(): void
    {
        // Only 'en' exists; requesting 'en_US' should fall back to 'en'.
        $en = new StubTranslation('en');
        $this->entity->getTranslations()->set('en', $en);

        self::assertSame($en, $this->entity->translate('en_US'));
    }

    #[Test]
    public function translateFallsBackToDefaultLocale(): void
    {
        $en = new StubTranslation('en');
        $this->entity->getTranslations()->set('en', $en);
        $this->entity->setDefaultLocalePublic('en');

        // Requesting 'de' with no 'de' or fallback → falls back to default 'en'.
        self::assertSame($en, $this->entity->translate('de'));
    }

    #[Test]
    public function translateReturnsNullWhenNoTranslationFound(): void
    {
        self::assertNull($this->entity->translate('fr'));
    }

    #[Test]
    public function translateWithFallbackDisabledReturnsNull(): void
    {
        $en = new StubTranslation('en');
        $this->entity->getTranslations()->set('en', $en);
        $this->entity->setDefaultLocalePublic('en');

        self::assertNull($this->entity->translate('de', false));
    }

    #[Test]
    public function translatePrefersExactOverLanguageFallback(): void
    {
        $enUs = new StubTranslation('en_US');
        $en = new StubTranslation('en');
        $this->entity->getTranslations()->set('en_US', $enUs);
        $this->entity->getTranslations()->set('en', $en);

        self::assertSame($enUs, $this->entity->translate('en_US'));
    }

    #[Test]
    public function translateWithNullLocaleUsesCurrentLocale(): void
    {
        $ru = new StubTranslation('ru');
        $this->entity->getTranslations()->set('ru', $ru);
        $this->entity->setCurrentLocalePublic('ru');

        self::assertSame($ru, $this->entity->translate(null));
    }

    #[Test]
    public function translateFallsBackToDefaultWhenCurrentLocaleHasNoTranslation(): void
    {
        $en = new StubTranslation('en');
        $this->entity->getTranslations()->set('en', $en);
        $this->entity->setCurrentLocalePublic('fr');
        $this->entity->setDefaultLocalePublic('en');

        self::assertSame($en, $this->entity->translate());
    }
}
