<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\EventSubscriber;

use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslationInterface;
use ChamberOrchestra\TranslationBundle\Contracts\Provider\LocaleProviderInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class TranslateSubscriber implements EventSubscriber
{
    public const LOCALE_FIELD_NAME = 'locale';
    private LocaleProviderInterface $localeProvider;

    public function __construct(LocaleProviderInterface $localeProvider)
    {
        $this->localeProvider = $localeProvider;
    }

    public function postLoad(PostLoadEventArgs $lifecycleEventArgs): void
    {
        $this->setLocales($lifecycleEventArgs);
    }

    public function prePersist(PrePersistEventArgs $lifecycleEventArgs): void
    {
        $this->setLocales($lifecycleEventArgs);
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
            Events::postLoad,
            Events::prePersist,
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();
        if (null === $classMetadata->reflClass) {
            // Class has not yet been fully built, ignore this event
            return;
        }

        if ($classMetadata->isMappedSuperclass) {
            return;
        }

        if (\is_a($classMetadata->reflClass->getName(), TranslatableInterface::class, true)) {
            $this->mapTranslatable($classMetadata);
        }

        if (\is_a($classMetadata->reflClass->getName(), TranslationInterface::class, true)) {
            $this->mapTranslation($classMetadata);
        }
    }

    private function mapTranslatable(ClassMetadata $metadata): void
    {
        if ($metadata->hasAssociation($fieldName = 'translations')) {
            return;
        }

        $metadata->mapOneToMany([
            'fieldName' => $fieldName,
            'mappedBy' => 'translatable',
            'indexBy' => self::LOCALE_FIELD_NAME,
            //            'cascade' => ['persist', 'merge', 'remove'],
            'cascade' => ['persist', 'remove'],
            'targetEntity' => $metadata->getReflectionClass()->getMethod(
                'getTranslationEntityClass'
            )->invoke(null),
            'cache' => $metadata->getAssociationCacheDefaults(
                $fieldName,
                ['usage' => ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE]
            ),
        ]);
    }

    private function mapTranslation(ClassMetadata $metadata): void
    {
        if (!$metadata->hasAssociation($fieldName = 'translatable')) {
            $mapping = [
                'fieldName' => $fieldName,
                'inversedBy' => 'translations',
                //                'cascade' => ['persist', 'merge'],
                'joinColumns' => [['nullable' => false, 'onDelete' => 'CASCADE']],
                'targetEntity' => $metadata->getReflectionClass()->getMethod(
                    'getTranslatableEntityClass'
                )->invoke(null),
                'cache' => $metadata->getAssociationCacheDefaults(
                    $fieldName,
                    ['usage' => ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE]
                ),
            ];

            $metadata->mapManyToOne($mapping);
        }

        $name = $metadata->getTableName().'_unique_translation';
        if (!$this->hasUniqueTranslationConstraint($metadata, $name)) {
            $metadata->table['uniqueConstraints'][$name] = [
                'columns' => ['translatable_id', self::LOCALE_FIELD_NAME],
            ];
        }
    }

    private function setLocales(LifecycleEventArgs $lifecycleEventArgs): void
    {
        // will take place on every entity
        $entity = $lifecycleEventArgs->getObject();
        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $currentLocale = $this->localeProvider->provideCurrentLocale();
        if (null !== $currentLocale) {
            $this->setField($lifecycleEventArgs, 'currentLocale', $currentLocale);
        }

        $fallbackLocale = $this->localeProvider->provideFallbackLocale();
        if (null !== $fallbackLocale) {
            $this->setField($lifecycleEventArgs, 'defaultLocale', $fallbackLocale);
        }
    }

    private function hasUniqueTranslationConstraint(ClassMetadata $classMetadataInfo, string $name): bool
    {
        if (!isset($classMetadataInfo->table['uniqueConstraints'])) {
            return false;
        }

        return isset($classMetadataInfo->table['uniqueConstraints'][$name]);
    }

    private function setField(LifecycleEventArgs $args, string $fieldName, string $value): void
    {
        $em = $args->getObjectManager();
        $entity = $args->getObject();
        $meta = $em->getClassMetadata(\get_class($entity));
        $refl = $meta->getReflectionClass();
        $property = $refl->getProperty($fieldName);
        $property->setValue($entity, $value);
    }
}
