<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Form\Loader;

interface LocalizationLoaderInterface
{
    public function load(string $key): ?string;
}
