<?php

declare(strict_types=1);

namespace Tests\Integrational\Entity;

use ChamberOrchestra\DoctrineExtensionsBundle\Entity\IdTrait;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use ChamberOrchestra\TranslationBundle\Entity\TranslatableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'test_post')]
class TestPost implements TranslatableInterface
{
    use IdTrait;
    use TranslatableTrait;

    #[ORM\Column]
    public string $slug = '';

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public static function getTranslationEntityClass(): string
    {
        return TestPostTranslation::class;
    }
}
