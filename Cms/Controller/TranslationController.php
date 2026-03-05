<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Controller;

use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Controller\SupportsDeleteOperation;
use ChamberOrchestra\CmsBundle\Controller\SupportsListOperation;
use ChamberOrchestra\CmsBundle\Controller\SupportsUpdateOperation;
use ChamberOrchestra\MenuBundle\Menu\MenuBuilder;
use ChamberOrchestra\TranslationBundle\Cms\EventSubscriber\WorkerRestartSubscriber;
use ChamberOrchestra\TranslationBundle\Cms\Form\Dto\TranslationDto;
use ChamberOrchestra\TranslationBundle\Cms\Form\TranslationFilterForm;
use ChamberOrchestra\TranslationBundle\Cms\Form\TranslationForm;
use ChamberOrchestra\TranslationBundle\Cms\Processor\TranslationProcessor;
use ChamberOrchestra\TranslationBundle\Entity\Translation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\UnicodeString;

#[Route('/translation', name: 'cms_translation_')]
#[IsGranted('ROLE_SUPER_USER')]
class TranslationController extends AbstractCrudController
{
    use SupportsListOperation;
    use SupportsUpdateOperation;
    use SupportsDeleteOperation;

    public function __construct(
        TranslationProcessor $processor,
        private readonly WorkerRestartSubscriber $restartSubscriber,
    ) {
        parent::__construct($processor, [
            'class' => Translation::class,
            'form_class' => TranslationForm::class,
            'data_class' => TranslationDto::class,
            'route_prefix' => 'cms_translation_',
            'create' => null,
            'meta' => null,
            'export' => null,
            'move' => null,
            'index' => [
                'fields' => [
                    'domain',
                    'message',
                    'value' => fn (?string $v): ?string => $v ? (string) (new UnicodeString($v))->truncate(60, '...') : null,
                    'context',
                    'exported',
                ],
                'orderBy' => ['exported' => 'ASC', 'createdDatetime' => 'DESC'],
                'filter' => TranslationFilterForm::class,
            ],
            'label_format' => 'translation.field.%name%',
            'title' => 'translation.title',
            'nav' => function (MenuBuilder $root): void {
                $root->add('cms_translation_export', [
                    'label' => $this->translator->trans('translation.nav.export', [], 'cms'),
                    'route' => 'cms_translation_export',
                ]);
            },
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        /** @var TranslationProcessor $processor */
        $processor = $this->processor;
        $processor->export();

        if ($this->restartSubscriber->isConfigured()) {
            $this->restartSubscriber->schedule();
        }

        return $this->redirectToRoute('cms_translation_index');
    }
}
