<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Repository;

use ChamberOrchestra\DoctrineExtensionsBundle\Repository\ServiceEntityRepository;
use ChamberOrchestra\TranslationBundle\Entity\Translation;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    public function findOneByKey(string $key, string $locale): ?Translation
    {
        if (!Uuid::isValid(TranslationHelper::parseId($key))) {
            return null;
        }

        return $this->findOneBy([
            'message' => TranslationHelper::getMessage($key),
            'locale' => $locale,
        ]);
    }
}
