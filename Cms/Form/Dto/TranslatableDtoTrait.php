<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Dto;

trait TranslatableDtoTrait
{
    public iterable $translations = [];

    public static function getTranslationDtoClass(): string
    {
        return \substr(static::class, 0, -3).'TranslationDto';
    }
}
