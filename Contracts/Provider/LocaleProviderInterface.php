<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Contracts\Provider;

interface LocaleProviderInterface
{
    public function provideCurrentLocale(): ?string;

    public function provideFallbackLocale(): ?string;
}
