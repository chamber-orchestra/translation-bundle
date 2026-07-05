<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Controller;

use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Processor\CrudProcessor;

/**
 * Base controller that automatically applies the localized (tabbed) view
 * for create and update actions. Extend this instead of AbstractCrudController
 * when the entity uses per-locale URL switching.
 */
abstract class AbstractLocalizedCrudController extends AbstractCrudController
{
    private const string VIEW = '@ChamberOrchestraTranslation/cms/update_localized.html.twig';

    protected function __construct(CrudProcessor $processor, array $options)
    {
        foreach (['create', 'update'] as $action) {
            if (!isset($options[$action]) || \is_array($options[$action])) {
                $options[$action] = \array_merge(['view' => self::VIEW], $options[$action] ?? []);
            }
        }

        parent::__construct($processor, $options);
    }
}
