<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form;

use ChamberOrchestra\CmsBundle\Form\Type\AbstractFilterType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class TranslationFilterForm extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', TextType::class, [
                'required' => false,
            ])
            ->add('message', TextType::class, [
                'required' => false,
            ]);
    }
}
