<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslationLocalesExtension extends AbstractExtension
{
    public function __construct(
        private readonly array $locales,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('translation_locales', fn() => $this->locales),
        ];
    }
}
