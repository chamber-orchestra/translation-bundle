<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Dto;

use ChamberOrchestra\CmsBundle\Form\Dto\AbstractDto;

abstract class AbstractTranslationDto extends AbstractDto
{
    public ?string $locale = null;
}
