<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Entity;

use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslatableInterface;
use ChamberOrchestra\TranslationBundle\Contracts\Entity\TranslationInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @mixin TranslatableInterface
 */
trait TranslatableTrait
{
    /**
     * @var Collection<string, TranslationInterface>|null
     *
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     */
    protected ?Collection $translations = null;
    protected string $currentLocale = 'en';
    protected string $defaultLocale = 'en';

    public static function getTranslationEntityClass(): string
    {
        return static::class.'Translation';
    }

    /**
     * @return Collection<string, TranslationInterface>
     */
    public function getTranslations(): Collection
    {
        return $this->translations ??= new ArrayCollection();
    }

    public function translate(?string $locale = null, bool $fallbackToDefault = true): ?TranslationInterface
    {
        return $this->doTranslate($locale, $fallbackToDefault);
    }

    protected function doTranslate(?string $locale = null, bool $fallbackToDefault = true): ?TranslationInterface
    {
        if (null === $locale) {
            $locale = $this->getCurrentLocale();
        }

        $translation = $this->findTranslationByLocale($locale);
        if (null !== $translation) {
            return $translation;
        }

        if ($fallbackToDefault) {
            $fallbackLocale = $this->computeFallbackLocale($locale);
            if (null !== $fallbackLocale && $translation = $this->findTranslationByLocale($fallbackLocale)) {
                return $translation;
            }

            $defaultTranslation = $this->findTranslationByLocale($this->defaultLocale);
            if (null !== $defaultTranslation) {
                return $defaultTranslation;
            }
        }

        return null;
    }

    protected function findTranslationByLocale(string $locale): ?TranslationInterface
    {
        return $this->getTranslations()->get($locale) ?: null;
    }

    /**
     * @return false|string
     */
    protected function computeFallbackLocale(string $locale): ?string
    {
        if (false !== \strrchr($locale, '_')) {
            return \substr($locale, 0, -\strlen(\strrchr($locale, '_')));
        }

        return null;
    }

    protected function getCurrentLocale(): string
    {
        return $this->currentLocale ?: $this->defaultLocale;
    }
}
