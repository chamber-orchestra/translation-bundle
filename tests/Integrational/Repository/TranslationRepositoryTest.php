<?php

declare(strict_types=1);

namespace Tests\Integrational\Repository;

use ChamberOrchestra\TranslationBundle\Entity\Translation;
use ChamberOrchestra\TranslationBundle\Repository\TranslationRepository;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class TranslationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TranslationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var TranslationRepository $repository */
        $repository = $this->em->getRepository(Translation::class);
        $this->repository = $repository;

        $schemaTool = new SchemaTool($this->em);
        try {
            $schemaTool->dropSchema([$this->em->getClassMetadata(Translation::class)]);
        } catch (\Throwable) {
            // Table may not exist on first run
        }
        $schemaTool->createSchema([
            $this->em->getClassMetadata(Translation::class),
        ]);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema([
            $this->em->getClassMetadata(Translation::class),
        ]);

        parent::tearDown();
    }

    #[Test]
    public function findOneByKeyReturnsTranslationForValidKey(): void
    {
        $uuid = Uuid::v7();
        $key = TranslationHelper::getLocalizationKey('messages', $uuid);
        $translation = Translation::create($key, 'Hello', 'en');

        $this->em->persist($translation);
        $this->em->flush();
        $this->em->clear();

        $found = $this->repository->findOneByKey($key, 'en');

        self::assertNotNull($found);
        self::assertSame('Hello', $found->getValue());
    }

    #[Test]
    public function findOneByKeyReturnsNullForNonExistentKey(): void
    {
        $uuid = Uuid::v7();
        $key = TranslationHelper::getLocalizationKey('messages', $uuid);

        self::assertNull($this->repository->findOneByKey($key, 'en'));
    }

    #[Test]
    public function findOneByKeyReturnsNullForInvalidUuid(): void
    {
        $invalidKey = 'messages@not-a-valid-uuid';

        self::assertNull($this->repository->findOneByKey($invalidKey, 'en'));
    }

    #[Test]
    public function findOneByKeyWorkWithPrefixedKey(): void
    {
        $uuid = Uuid::v7();
        $key = TranslationHelper::getLocalizationKey('cms', $uuid, 'form', 'title');
        $translation = Translation::create($key, 'My Title', 'en');

        $this->em->persist($translation);
        $this->em->flush();
        $this->em->clear();

        $found = $this->repository->findOneByKey($key, 'en');

        self::assertNotNull($found);
        self::assertSame('My Title', $found->getValue());
    }

    #[Test]
    public function findByExportedFalseReturnsPendingTranslations(): void
    {
        $uuid1 = Uuid::v7();
        $uuid2 = Uuid::v7();

        $pending = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid1),
            'pending',
            'en',
        );
        $exported = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid2),
            'exported',
            'en',
        );
        $exported->export();

        $this->em->persist($pending);
        $this->em->persist($exported);
        $this->em->flush();
        $this->em->clear();

        $results = $this->repository->findBy(['exported' => false]);

        self::assertCount(1, $results);
        self::assertSame('pending', $results[0]->getValue());
    }
}
