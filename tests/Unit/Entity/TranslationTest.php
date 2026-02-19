<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use ChamberOrchestra\TranslationBundle\Entity\Translation;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TranslationTest extends TestCase
{
    private Uuid $uuid;
    private string $simpleKey;

    protected function setUp(): void
    {
        $this->uuid = Uuid::fromString('01939f63-1234-7abc-9def-000000000001');
        $this->simpleKey = TranslationHelper::getLocalizationKey('messages', $this->uuid);
    }

    #[Test]
    public function createPopulatesAllFields(): void
    {
        $translation = Translation::create($this->simpleKey, 'Hello', 'some context');

        self::assertSame('messages', $translation->getDomain());
        self::assertSame((string) $this->uuid, $translation->getMessage());
        self::assertSame('Hello', $translation->getValue());
        self::assertSame('some context', $translation->getContext());
        self::assertFalse($translation->isExported());
    }

    #[Test]
    public function createWithNullContextDefaultsToNull(): void
    {
        $translation = Translation::create($this->simpleKey, 'Hello');

        self::assertNull($translation->getContext());
    }

    #[Test]
    public function createWithPrefixedKeyStoresFullMessage(): void
    {
        $key = TranslationHelper::getLocalizationKey('cms', $this->uuid, 'form', 'title');
        $translation = Translation::create($key, 'Title');

        self::assertSame('cms', $translation->getDomain());
        self::assertSame(\sprintf('form.title.%s', $this->uuid), $translation->getMessage());
    }

    #[Test]
    public function createAssignsIdFromKey(): void
    {
        $translation = Translation::create($this->simpleKey, 'value');

        // The entity id is the UUID extracted from the key.
        self::assertTrue($this->uuid->equals($translation->getId()));
    }

    #[Test]
    public function updateChangesValue(): void
    {
        $translation = Translation::create($this->simpleKey, 'original');
        $translation->update('updated');

        self::assertSame('updated', $translation->getValue());
    }

    #[Test]
    public function updateDoesNotChangeOtherFields(): void
    {
        $translation = Translation::create($this->simpleKey, 'original', 'ctx');
        $translation->update('updated');

        self::assertSame('messages', $translation->getDomain());
        self::assertSame('ctx', $translation->getContext());
    }

    #[Test]
    public function exportSetsExportedFlag(): void
    {
        $translation = Translation::create($this->simpleKey, 'Hello');
        self::assertFalse($translation->isExported());

        $translation->export();

        self::assertTrue($translation->isExported());
    }

    #[Test]
    public function markAsNeedToExportClearsExportedFlag(): void
    {
        $translation = Translation::create($this->simpleKey, 'Hello');
        $translation->export();
        self::assertTrue($translation->isExported());

        $translation->markAsNeedToExport();

        self::assertFalse($translation->isExported());
    }

    #[Test]
    public function exportAndMarkCycleWorks(): void
    {
        $translation = Translation::create($this->simpleKey, 'Hello');

        $translation->export();
        self::assertTrue($translation->isExported());

        $translation->markAsNeedToExport();
        self::assertFalse($translation->isExported());

        $translation->export();
        self::assertTrue($translation->isExported());
    }
}
