<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Form\Loader;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[Autoconfigure(lazy: true)]
class LocalizationLoaderChain implements LocalizationLoaderInterface
{
    public function __construct(
        #[AutowireIterator('localization.loader', exclude: [self::class])]
        private readonly iterable $loaders,
    ) {
    }

    public function load(string $key): string
    {
        /** @var LocalizationLoaderInterface $loader */
        foreach ($this->loaders as $loader) {
            if (null !== $value = $loader->load($key)) {
                return $value;
            }
        }

        return $key;
    }
}
