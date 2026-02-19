<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Entity;

interface TranslatableInterface
{
    public static function indexBy(): string;
}
