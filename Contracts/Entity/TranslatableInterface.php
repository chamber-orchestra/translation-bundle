<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Contracts\Entity;

use Doctrine\Common\Collections\Collection;

interface TranslatableInterface
{
    public static function getTranslationEntityClass(): string;

    public function translate(?string $locale = null, bool $fallbackToDefault = true): ?TranslationInterface;

    public function getTranslations(): Collection;
}
