<?php

declare(strict_types=1);

namespace Tests\Integrational\Command;

use ChamberOrchestra\TranslationBundle\Entity\Translation;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class ExportTranslationCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $translationsDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        try {
            $schemaTool->dropSchema([$this->em->getClassMetadata(Translation::class)]);
        } catch (\Throwable) {
            // Table may not exist on first run
        }
        $schemaTool->createSchema([
            $this->em->getClassMetadata(Translation::class),
        ]);

        // Translations directory as configured in TestKernel.
        $this->translationsDir = self::getContainer()->getParameter('kernel.project_dir').'/tests/translations';
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema([
            $this->em->getClassMetadata(Translation::class),
        ]);

        // Clean up any generated YAML files.
        foreach (\glob($this->translationsDir.'/*.yaml') ?: [] as $file) {
            @\unlink($file);
        }

        parent::tearDown();
    }

    private function runCommand(): int
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('translation:export'));

        return $tester->execute([]);
    }

    #[Test]
    public function commandSucceedsWithNoUnexportedTranslations(): void
    {
        $exitCode = $this->runCommand();

        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function commandMarksTranslationsAsExported(): void
    {
        $uuid = Uuid::v7();
        $key = TranslationHelper::getLocalizationKey('messages', $uuid);
        $translation = Translation::create($key, 'Hello, world!', 'ru');

        $this->em->persist($translation);
        $this->em->flush();

        self::assertFalse($translation->isExported());

        $this->runCommand();
        $this->em->refresh($translation);

        self::assertTrue($translation->isExported());
    }

    #[Test]
    public function commandWritesXliffFile(): void
    {
        $uuid = Uuid::v7();
        $key = TranslationHelper::getLocalizationKey('messages', $uuid);
        $translation = Translation::create($key, 'Exported value', 'ru');

        $this->em->persist($translation);
        $this->em->flush();

        $this->runCommand();

        $yamlFile = $this->translationsDir.'/messages+intl-icu.ru.yaml';
        self::assertFileExists($yamlFile);
        self::assertStringContainsString('Exported value', \file_get_contents($yamlFile));
    }

    #[Test]
    public function commandSkipsAlreadyExportedTranslations(): void
    {
        $uuid1 = Uuid::v7();
        $uuid2 = Uuid::v7();

        $pending = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid1),
            'Pending value',
            'ru',
        );
        $alreadyExported = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid2),
            'Already exported',
            'ru',
        );
        $alreadyExported->export();

        $this->em->persist($pending);
        $this->em->persist($alreadyExported);
        $this->em->flush();

        $this->runCommand();
        $this->em->refresh($pending);
        $this->em->refresh($alreadyExported);

        // Both should be exported now, but the one that was already exported
        // should not have been re-processed (it was skipped in the query).
        self::assertTrue($pending->isExported());
        self::assertTrue($alreadyExported->isExported());
    }

    #[Test]
    public function commandGroupsTranslationsByDomain(): void
    {
        $uuid1 = Uuid::v7();
        $uuid2 = Uuid::v7();

        $messagesTranslation = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid1),
            'Hello',
            'ru',
        );
        $validatorsTranslation = Translation::create(
            TranslationHelper::getLocalizationKey('validators', $uuid2),
            'Required field',
            'ru',
        );

        $this->em->persist($messagesTranslation);
        $this->em->persist($validatorsTranslation);
        $this->em->flush();

        $this->runCommand();

        self::assertFileExists($this->translationsDir.'/messages+intl-icu.ru.yaml');
        self::assertFileExists($this->translationsDir.'/validators+intl-icu.ru.yaml');
    }

    #[Test]
    public function commandAppendsToExistingXliffFile(): void
    {
        // First run: export one translation.
        $uuid1 = Uuid::v7();
        $first = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid1),
            'First value',
            'ru',
        );
        $this->em->persist($first);
        $this->em->flush();
        $this->runCommand();

        $yamlFile = $this->translationsDir.'/messages+intl-icu.ru.yaml';
        self::assertFileExists($yamlFile);

        // Second run: export another translation.
        $uuid2 = Uuid::v7();
        $second = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid2),
            'Second value',
            'ru',
        );
        $this->em->persist($second);
        $this->em->flush();
        $this->runCommand();

        $content = \file_get_contents($yamlFile);
        self::assertStringContainsString('First value', $content);
        self::assertStringContainsString('Second value', $content);
    }
}
