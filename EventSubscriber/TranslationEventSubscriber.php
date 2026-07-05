<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\EventSubscriber;

use ChamberOrchestra\TranslationBundle\Entity\Translation;
use ChamberOrchestra\TranslationBundle\Events\TranslationEvent;
use ChamberOrchestra\TranslationBundle\Repository\TranslationRepository;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(TranslationEvent::class)]
class TranslationEventSubscriber
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslationRepository $repository,
    ) {
    }

    public function __invoke(TranslationEvent $event): void
    {
        if (!Uuid::isValid(TranslationHelper::parseId($event->key))) {
            return;
        }

        $translation = $this->repository->findOneByKey($event->key, $event->locale);
        if (null !== $translation && $event->value === $translation->getValue()) {
            return;
        }

        if (null === $translation) {
            if ('' === $event->value) {
                return;
            }
            $translation = Translation::create($event->key, $event->value, $event->locale, $event->context);
        } else {
            $translation->update($event->value);
            $translation->markAsNeedToExport();
        }

        $this->em->persist($translation);
        $this->em->flush();
    }
}
