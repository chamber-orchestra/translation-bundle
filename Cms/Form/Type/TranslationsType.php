<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\DtoCollection;
use ChamberOrchestra\TranslationBundle\Cms\Form\Dto\AbstractTranslationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslationsType extends AbstractType
{
    private array $locales = [];

    public function __construct(array $locales = ['ru', 'en'])
    {
        $this->locales = $locales;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_add' => false,
            'allow_delete' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = \array_replace_recursive($view->vars, [
            'locales' => $this->locales,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locales = $this->locales;
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($locales): void {
            /** @var DtoCollection|AbstractTranslationDto[] $data */
            // predifine locales
            $data = $event->getData();
            $localeData = clone $data;
            foreach ($locales as $locale) {
                if (!$localeData->containsKey($locale)) {
                    // fill with locales
                    $localeData->set($locale, null);
                }
            }

            $event->setData($localeData);
        }, 32);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var DtoCollection|AbstractTranslationDto[] $data */
            $data = $event->getData();

            foreach ($data as $key => $value) {
                $value->locale = $key;
            }

            $event->setData($data);
        });
    }

    public function getParent(): ?string
    {
        return CollectionType::class;
    }
}
