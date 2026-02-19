<?php

declare(strict_types=1);

namespace Tests\Integrational\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Integrational\Entity\TestPost;
use Tests\Integrational\Entity\TestPostTranslation;

/**
 * Tests TranslateSubscriber: ORM auto-mapping of translatable/translation associations
 * and locale injection on postLoad/prePersist.
 */
final class TranslateSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema([
            $this->em->getClassMetadata(TestPost::class),
            $this->em->getClassMetadata(TestPostTranslation::class),
        ]);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema([
            $this->em->getClassMetadata(TestPost::class),
            $this->em->getClassMetadata(TestPostTranslation::class),
        ]);

        parent::tearDown();
    }

    #[Test]
    public function translatableHasTranslationsAssociationMapped(): void
    {
        $metadata = $this->em->getClassMetadata(TestPost::class);

        self::assertTrue($metadata->hasAssociation('translations'));
    }

    #[Test]
    public function translationHasTranslatableAssociationMapped(): void
    {
        $metadata = $this->em->getClassMetadata(TestPostTranslation::class);

        self::assertTrue($metadata->hasAssociation('translatable'));
    }

    #[Test]
    public function translationTableHasUniqueConstraint(): void
    {
        $metadata = $this->em->getClassMetadata(TestPostTranslation::class);
        $constraintName = $metadata->getTableName().'_unique_translation';

        self::assertArrayHasKey($constraintName, $metadata->table['uniqueConstraints'] ?? []);
    }

    #[Test]
    public function persistAndLoadTranslation(): void
    {
        $post = new TestPost();
        $translation = new TestPostTranslation($post, 'en', 'Hello World');
        $post->getTranslations()->set('en', $translation);

        $this->em->persist($post);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(TestPost::class, $post->getId());
        self::assertNotNull($loaded);

        $loadedTranslation = $loaded->getTranslations()->get('en');
        self::assertNotNull($loadedTranslation);
        self::assertSame('Hello World', $loadedTranslation->title);
    }

    #[Test]
    public function translateReturnsCorrectTranslationByLocale(): void
    {
        $post = new TestPost();
        $enTranslation = new TestPostTranslation($post, 'en', 'English');
        $ruTranslation = new TestPostTranslation($post, 'ru', 'Russian');
        $post->getTranslations()->set('en', $enTranslation);
        $post->getTranslations()->set('ru', $ruTranslation);

        $this->em->persist($post);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(TestPost::class, $post->getId());
        self::assertNotNull($loaded);

        self::assertSame('English', $loaded->translate('en')->title);
        self::assertSame('Russian', $loaded->translate('ru')->title);
    }

    #[Test]
    public function translationsCascadeDeleteWithPost(): void
    {
        $post = new TestPost();
        $translation = new TestPostTranslation($post, 'en', 'Delete me');
        $post->getTranslations()->set('en', $translation);

        $this->em->persist($post);
        $this->em->flush();

        $translationId = $translation->getId();

        $this->em->remove($post);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(TestPostTranslation::class, $translationId));
    }

    #[Test]
    public function localeIsInjectedOnPostLoad(): void
    {
        // The kernel runs with locale 'en' (configured in TestKernel).
        // After postLoad the entity's currentLocale should be set by TranslateSubscriber.
        $post = new TestPost();
        $this->em->persist($post);
        $this->em->flush();
        $id = $post->getId();
        $this->em->clear();

        $loaded = $this->em->find(TestPost::class, $id);
        self::assertNotNull($loaded);

        // TranslateSubscriber injects defaultLocale from kernel.default_locale = 'en' on postLoad.
        // We verify by reading the protected field via reflection.
        $refl = new \ReflectionProperty($loaded, 'defaultLocale');
        self::assertSame('en', $refl->getValue($loaded));
    }
}
