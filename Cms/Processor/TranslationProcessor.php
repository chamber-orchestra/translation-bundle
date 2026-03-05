<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Processor;

use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Processor\CrudProcessor;
use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use ChamberOrchestra\CmsBundle\Processor\Utils\CrudUtils;
use ChamberOrchestra\TranslationBundle\Entity\Translation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TranslationProcessor extends CrudProcessor
{
    public function __construct(
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        CrudUtils $utils,
        Instantiator $instantiator,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct($em, $dispatcher, $utils, $instantiator);
    }

    public function update(DtoInterface $dto, object $entity): void
    {
        /* @var Translation $entity */
        $entity->markAsNeedToExport();
        parent::update($dto, $entity);
    }

    public function export(): void
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $app->run(new ArrayInput(['command' => 'translation:export']), new NullOutput());
    }
}
