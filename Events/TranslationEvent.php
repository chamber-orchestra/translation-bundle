<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

class TranslationEvent extends Event
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly string $locale,
        public readonly ?string $context = null,
    ) {
    }
}
