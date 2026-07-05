<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Dto;

use ChamberOrchestra\CmsBundle\Form\Dto\AbstractDto;
use ChamberOrchestra\TranslationBundle\Entity\Translation;

class TranslationDto extends AbstractDto
{
    public ?string $value = null;
    public ?string $context = null;

    public function __construct()
    {
        parent::__construct(Translation::class);
    }
}
