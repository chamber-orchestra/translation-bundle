<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class TranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('locale', HiddenType::class, [
            'required' => true,
        ]);
    }
}
