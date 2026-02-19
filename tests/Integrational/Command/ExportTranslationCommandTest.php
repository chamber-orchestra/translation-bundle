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

        // Clean up any generated XLIFF files.
        foreach (\glob($this->translationsDir.'/*.xliff') ?: [] as $file) {
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
        $translation = Translation::create($key, 'Hello, world!');

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
        $translation = Translation::create($key, 'Exported value');

        $this->em->persist($translation);
        $this->em->flush();

        $this->runCommand();

        $xliffFile = $this->translationsDir.'/messages+intl-icu.ru.xliff';
        self::assertFileExists($xliffFile);
        self::assertStringContainsString('Exported value', \file_get_contents($xliffFile));
    }

    #[Test]
    public function commandSkipsAlreadyExportedTranslations(): void
    {
        $uuid1 = Uuid::v7();
        $uuid2 = Uuid::v7();

        $pending = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid1),
            'Pending value',
        );
        $alreadyExported = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid2),
            'Already exported',
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
        );
        $validatorsTranslation = Translation::create(
            TranslationHelper::getLocalizationKey('validators', $uuid2),
            'Required field',
        );

        $this->em->persist($messagesTranslation);
        $this->em->persist($validatorsTranslation);
        $this->em->flush();

        $this->runCommand();

        self::assertFileExists($this->translationsDir.'/messages+intl-icu.ru.xliff');
        self::assertFileExists($this->translationsDir.'/validators+intl-icu.ru.xliff');
    }

    #[Test]
    public function commandAppendsToExistingXliffFile(): void
    {
        // First run: export one translation.
        $uuid1 = Uuid::v7();
        $first = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid1),
            'First value',
        );
        $this->em->persist($first);
        $this->em->flush();
        $this->runCommand();

        $xliffFile = $this->translationsDir.'/messages+intl-icu.ru.xliff';
        self::assertFileExists($xliffFile);

        // Second run: export another translation.
        $uuid2 = Uuid::v7();
        $second = Translation::create(
            TranslationHelper::getLocalizationKey('messages', $uuid2),
            'Second value',
        );
        $this->em->persist($second);
        $this->em->flush();
        $this->runCommand();

        $content = \file_get_contents($xliffFile);
        self::assertStringContainsString('First value', $content);
        self::assertStringContainsString('Second value', $content);
    }
}
