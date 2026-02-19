<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Entity;

use ChamberOrchestra\DoctrineExtensionsBundle\Entity\IdTrait;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslationInterface;
use Symfony\Component\Uid\Uuid;

abstract class AbstractTranslation implements TranslationInterface
{
    use IdTrait;
    use TranslationTrait;

    public function __construct(TranslatableInterface $translatable, string $locale)
    {
        $this->id = Uuid::v4();
        $this->translatable = $translatable;
        $this->locale = $locale;
    }
}
