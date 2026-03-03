<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form;

use ChamberOrchestra\TranslationBundle\Cms\Form\Dto\TranslationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TranslationForm extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TranslationDto::class,
            'translation_domain' => 'cms',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', TextareaType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'attr' => ['rows' => 5],
            ])
            ->add('context', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(['max' => 512]),
                ],
            ]);
    }
}
