<?php

declare(strict_types=1);

namespace Tests\Integrational\Entity;

use ChamberOrchestra\DoctrineExtensionsBundle\Entity\IdTrait;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslationInterface;
use ChamberOrchestra\TranslationBundle\Entity\TranslationTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'test_post_translation')]
class TestPostTranslation implements TranslationInterface
{
    use IdTrait;
    use TranslationTrait;

    #[ORM\Column]
    public string $title = '';

    public function __construct(TestPost $post, string $locale, string $title = '')
    {
        $this->id = Uuid::v7();
        $this->translatable = $post;
        $this->locale = $locale;
        $this->title = $title;
    }

    // TranslationTrait::getTranslatableEntityClass() derives "TestPost" from "TestPostTranslation"
    // by stripping the "Translation" suffix — works correctly for this namespace.
}
