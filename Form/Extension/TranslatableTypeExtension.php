<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Form\Extension;

use ChamberOrchestra\CmsBundle\Form\Type\WysiwygType;
use ChamberOrchestra\TranslationBundle\Contracts\Provider\LocaleProviderInterface;
use ChamberOrchestra\TranslationBundle\Events\TranslationEvent;
use ChamberOrchestra\TranslationBundle\Form\Loader\LocalizationLoaderChain;
use ChamberOrchestra\TranslationBundle\Form\Loader\LocalizationLoaderInterface;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Uid\Uuid;

class TranslatableTypeExtension extends AbstractTypeExtension
{
    private const HOLDER = 'localization/map';
    /** @var array<string, array{key: string, existing: bool}> */
    private array $map = [];

    public function __construct(
        private readonly LocalizationLoaderChain $loader,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LocaleProviderInterface $localeProvider,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [TextType::class, TextareaType::class, WysiwygType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined([
            'localization',
            'localization_domain', // the localization domain, default is "entity"
            'localization_context', // to provide the context for the translation,
            'localization_loader',
        ]);
        $resolver->setAllowedTypes('localization_loader', LocalizationLoaderInterface::class);
        $resolver->setDefaults([
            'localization' => false,
            'localization_domain' => 'entity',
            'localization_context' => null,
            'localization_loader' => $this->loader,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setAttribute(self::HOLDER, \uniqid());

        if (isset($options['localization']) && $options['localization']) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
                /**
                 * Reuse the existing key if passed.
                 */
                $id = $event->getForm()->getConfig()->getAttribute(self::HOLDER);
                $existing = (bool) $event->getData();
                $key = $existing ? $event->getData() : $this->generateLocalizationKey($event, $options);
                $this->map[$id] = ['key' => $key, 'existing' => $existing];
                if ($existing) {
                    $event->setData($options['localization_loader']->load($key));
                }
                $event->stopPropagation();
            });

            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
                $id = $event->getForm()->getConfig()->getAttribute(self::HOLDER);
                ['key' => $key, 'existing' => $existing] = $this->map[$id];

                $value = $this->normalizeValue($event->getData());

                // Empty value on a field that never had a key: leave the entity field null,
                // do not generate a translation key and do not create a Translation record.
                if ('' === $value && !$existing) {
                    $event->setData(null);
                    $event->stopPropagation();

                    return;
                }

                $event->setData($key);

                $locale = $this->localeProvider->provideCurrentLocale()
                    ?? $this->localeProvider->provideFallbackLocale()
                    ?? 'en';

                $this->dispatcher->dispatch(new TranslationEvent($key, $value, $locale, $this->getContext($event, $options)));
                $event->stopPropagation();
            });
        }
    }

    private function normalizeValue(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        // TinyMCE inserts <p><br data-mce-bogus="1"></p> as a placeholder for an empty editor.
        // Treat this specific pattern as an empty string so it is not persisted as a translation.
        if (\preg_match('/^\s*<p[^>]*>\s*<br[^>]+data-mce-bogus[^>]*>\s*<\/p>\s*$/i', $value)) {
            return '';
        }

        return $value;
    }

    private function generateLocalizationKey(FormEvent $event, array $options): string
    {
        /*
         * The key could be in the formats
         * "domain@prefix.name.id"
         * "domain@name.id"
         *
         * Where
         * - id - unique value for the translation, stored in the database
         * - name - label for the translation
         * - prefix - could be any string, but the initial idea was to link it with the entity
         */
        return TranslationHelper::getLocalizationKey(
            $this->searchOption($event, 'localization_domain'),
            Uuid::v7(),
        );
    }

    private function getContext(FormEvent $event): string
    {
        $form = $event->getForm();
        $values = [];
        do {
            $values[] = $form->getConfig()->getOption('localization_context') ?? $form->getName();
            $form = $form->getParent();
        } while ($form);

        return \implode('.', $values);
    }

    private function searchOption(FormEvent $event, string $option): ?string
    {
        $form = $event->getForm();
        do {
            $value = $form->getConfig()->getOption($option);
            $form = $form->getParent();
        } while (!$value && $form);

        return $value;
    }
}
