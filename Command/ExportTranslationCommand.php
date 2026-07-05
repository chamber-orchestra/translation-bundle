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
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'translation:export')]
class ExportTranslationCommand extends Command
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $translationsPath,
        private readonly EntityManagerInterface $em,
        private readonly array $locales,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $er = $this->em->getRepository(Translation::class);
        $grouped = $this->groupByDomainAndLocale($er->findBy(['exported' => false]));

        $this->em->beginTransaction();
        try {
            foreach ($grouped as $domain => $byLocale) {
                foreach ($byLocale as $locale => $translations) {
                    $file = \sprintf('%s/%s+intl-icu.%s.yaml', $this->translationsPath, $domain, $locale);
                    $existing = \file_exists($file) ? (Yaml::parseFile($file) ?? []) : [];
                    $flat = $this->flattenYaml($existing);

                    foreach ($translations as $translation) {
                        $key = TranslationHelper::getLocalizationKey($domain, Uuid::fromString($translation->getMessage()));
                        $flat[$key] = $translation->getValue();

                        $translation->export();
                        $this->em->persist($translation);
                    }

                    \file_put_contents($file, Yaml::dump($this->toNested($flat), 10, 2));
                }
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

    /**
     * @param Translation[] $translations
     *
     * @return array<string, array<string, Translation[]>>
     */
    private function groupByDomainAndLocale(array $translations): array
    {
        $grouped = [];

        foreach ($translations as $translation) {
            $grouped[$translation->getDomain()][$translation->getLocale()][] = $translation;
        }

        return $grouped;
    }

    private function flattenYaml(array $nested, string $prefix = ''): array
    {
        $result = [];
        foreach ($nested as $key => $value) {
            $fullKey = '' !== $prefix ? $prefix.'.'.$key : (string) $key;
            if (\is_array($value)) {
                $result = \array_merge($result, $this->flattenYaml($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    private function toNested(array $flat): array
    {
        $result = [];
        foreach ($flat as $key => $value) {
            $parts = \explode('.', (string) $key);
            $ref = &$result;
            foreach ($parts as $part) {
                if (!\array_key_exists($part, $ref) || !\is_array($ref[$part])) {
                    $ref[$part] = [];
                }
                $ref = &$ref[$part];
            }
            $ref = $value;
        }

        return $result;
    }
}
