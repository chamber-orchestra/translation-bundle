<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Command;

use ChamberOrchestra\TranslationBundle\Entity\Translation;
use ChamberOrchestra\TranslationBundle\Events\TranslationExportedEvent;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'translation:export')]
class ExportTranslationCommand extends Command
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $translationsPath,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ext = 'xliff';
        $version = '2.0';
        $locale = 'ru';
        $options = ['xliff_version' => $version];
        $dumper = new XliffFileDumper($ext);

        $er = $this->em->getRepository(Translation::class);
        $grouped = $this->groupByDomain($er->findBy(['exported' => false]));
        /* @var Translation $translation */
        $this->em->beginTransaction();
        try {
            foreach ($grouped as $domain => $translations) {
                $file = \sprintf('%s/%s+intl-icu.ru.xliff', $this->translationsPath, $domain);
                if (\file_exists($file)) {
                    $catalogue = (new XliffFileLoader())->load($file, $locale, $domain);
                } else {
                    $catalogue = new MessageCatalogue($locale);
                }
                foreach ($translations as $translation) {
                    $id = TranslationHelper::getLocalizationKey($domain, Uuid::fromString($translation->getMessage()));
                    $value = $translation->getValue();
                    $context = $translation->getContext();
                    $notes = ['notes' => [['category' => 'context', 'content' => $context]]];
                    $catalogue->set($id, $value, $domain);
                    $catalogue->setMetadata($id, $notes, $domain);

                    $translation->export();
                    $this->em->persist($translation);
                }
                $dump = $dumper->formatCatalogue($catalogue, $domain, $options);
                \file_put_contents($file, $dump);
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $this->dispatcher->dispatch(new TranslationExportedEvent());

        return self::SUCCESS;
    }

    private function groupByDomain(array $translations): array
    {
        $grouped = [];

        /* @var Translation $translation */
        foreach ($translations as $translation) {
            $grouped[$translation->getDomain()][] = $translation;
        }

        return $grouped;
    }
}
