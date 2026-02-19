<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

trait TranslatableTypeTrait
{
    public function addTranslatableChildren(FormBuilderInterface $builder, string $type, array $options = []): void
    {
        $builder->add('translations', TranslationsType::class, \array_replace_recursive([
            'entry_type' => $type,
        ], $options));
    }
}
