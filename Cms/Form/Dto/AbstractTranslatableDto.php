<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Dto;

use ChamberOrchestra\CmsBundle\Form\Dto\AbstractDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoCollection;

abstract class AbstractTranslatableDto extends AbstractDto
{
    use TranslatableDtoTrait;

    public function __construct(?string $typeClass = null, ?string $translationDtoClass = null)
    {
        $this->translations = new DtoCollection($translationDtoClass ?: self::getTranslationDtoClass());
        parent::__construct($typeClass);
    }
}
