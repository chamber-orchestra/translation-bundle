<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Contracts\Entity;

interface TranslationInterface
{
    public static function getTranslatableEntityClass(): string;

    public function getLocale(): string;
}
