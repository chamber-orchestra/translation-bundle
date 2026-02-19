<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Entity;

use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use Doctrine\ORM\Mapping as ORM;

trait TranslationTrait
{
    #[ORM\Column(length: 5)]
    protected string $locale = 'en';

    // mapped programmatically by TranslateSubscriber (ManyToOne + CASCADE DELETE)
    protected TranslatableInterface $translatable;

    public static function getTranslatableEntityClass(): string
    {
        return \substr(static::class, 0, -11);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
