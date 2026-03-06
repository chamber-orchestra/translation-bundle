<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Form\Loader;

use ChamberOrchestra\TranslationBundle\Contracts\Provider\LocaleProviderInterface;
use ChamberOrchestra\TranslationBundle\Repository\TranslationRepository;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Uid\Uuid;

#[Autoconfigure(tags: ['localization.loader'])]
#[AsTaggedItem(priority: 10)]
final class DatabaseLocalizationLoader implements LocalizationLoaderInterface
{
    public function __construct(
        private readonly TranslationRepository $repository,
        private readonly LocaleProviderInterface $localeProvider,
    ) {}

    public function load(string $key): ?string
    {
        if (!Uuid::isValid(TranslationHelper::parseId($key))) {
            return null;
        }

        $locale = $this->localeProvider->provideCurrentLocale()
            ?? $this->localeProvider->provideFallbackLocale();

        if (null === $locale) {
            return null;
        }

        return $this->repository->findOneByKey($key, $locale)?->getValue();
    }
}
