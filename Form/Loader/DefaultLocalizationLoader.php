<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Form\Loader;

use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Autoconfigure(tags: ['localization.loader'])]
#[AsTaggedItem(priority: 0)]
class DefaultLocalizationLoader implements LocalizationLoaderInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function load(string $key): string
    {
        return $this->translator->trans($key, [], TranslationHelper::getDomain($key));
    }
}
