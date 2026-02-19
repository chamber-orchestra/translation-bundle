<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TranslationHelperTest extends TestCase
{
    private Uuid $uuid;

    protected function setUp(): void
    {
        $this->uuid = Uuid::fromString('01939f63-1234-7abc-9def-000000000001');
    }

    #[Test]
    public function getLocalizationKeyWithNoParts(): void
    {
        $key = TranslationHelper::getLocalizationKey('messages', $this->uuid);

        self::assertSame('messages@01939f63-1234-7abc-9def-000000000001', $key);
    }

    #[Test]
    public function getLocalizationKeyWithOnePart(): void
    {
        $key = TranslationHelper::getLocalizationKey('messages', $this->uuid, 'field');

        self::assertSame('messages@field.01939f63-1234-7abc-9def-000000000001', $key);
    }

    #[Test]
    public function getLocalizationKeyWithMultipleParts(): void
    {
        $key = TranslationHelper::getLocalizationKey('cms', $this->uuid, 'form', 'title');

        self::assertSame('cms@form.title.01939f63-1234-7abc-9def-000000000001', $key);
    }

    #[Test]
    public function getLocalizationKeyFiltersEmptyParts(): void
    {
        $key = TranslationHelper::getLocalizationKey('messages', $this->uuid, '', 'field', '');

        // empty parts are filtered; UUID is always appended last
        self::assertSame('messages@field.01939f63-1234-7abc-9def-000000000001', $key);
    }

    #[Test]
    public function uuidIsAlwaysLastSegmentEvenWithParts(): void
    {
        $key = TranslationHelper::getLocalizationKey('messages', $this->uuid, 'prefix');

        self::assertStringEndsWith((string) $this->uuid, $key);
    }

    #[Test]
    public function parseIdWithNoPrefix(): void
    {
        $key = \sprintf('messages@%s', $this->uuid);

        self::assertSame((string) $this->uuid, TranslationHelper::parseId($key));
    }

    #[Test]
    public function parseIdWithSinglePrefix(): void
    {
        $key = \sprintf('messages@field.%s', $this->uuid);

        self::assertSame((string) $this->uuid, TranslationHelper::parseId($key));
    }

    #[Test]
    public function parseIdWithMultiplePrefixes(): void
    {
        $key = \sprintf('cms@form.title.%s', $this->uuid);

        self::assertSame((string) $this->uuid, TranslationHelper::parseId($key));
    }

    #[Test]
    public function getIdReturnsCorrectUuid(): void
    {
        $key = TranslationHelper::getLocalizationKey('messages', $this->uuid);

        self::assertTrue($this->uuid->equals(TranslationHelper::getId($key)));
    }

    #[Test]
    public function roundTripPreservesUuidWithParts(): void
    {
        $key = TranslationHelper::getLocalizationKey('cms', $this->uuid, 'form', 'title');

        self::assertTrue($this->uuid->equals(TranslationHelper::getId($key)));
    }

    #[Test]
    public function getDomainReturnsPartBeforeAt(): void
    {
        $key = \sprintf('messages@field.%s', $this->uuid);

        self::assertSame('messages', TranslationHelper::getDomain($key));
    }

    #[Test]
    public function getMessageReturnsPartAfterAt(): void
    {
        $key = \sprintf('messages@field.%s', $this->uuid);

        self::assertSame(\sprintf('field.%s', $this->uuid), TranslationHelper::getMessage($key));
    }
}
