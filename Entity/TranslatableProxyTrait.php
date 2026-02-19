<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Entity;

/**
 * @mixin TranslatableTrait
 */
trait TranslatableProxyTrait
{
    protected function proxyCurrentLocaleTranslation(string $method, array $arguments = [])
    {
        // allow $entity->name call $entity->getName() in templates
        if (!\method_exists(self::getTranslationEntityClass(), $method)) {
            $method = 'get'.\ucfirst($method);
        }

        $translation = $this->translate($this->getCurrentLocale());
        if (null === $translation) {
            return null;
        }

        return \call_user_func_array([$translation, $method], $arguments);
    }
}
