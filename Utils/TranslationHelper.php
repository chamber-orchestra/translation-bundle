<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Utils;

use Symfony\Component\Uid\Uuid;

class TranslationHelper
{
    public static function getLocalizationKey(string $domain, Uuid $id, string ...$parts): string
    {
        $segments = \array_filter($parts);
        $segments[] = (string) $id;

        return \sprintf('%s@%s', $domain, \implode('.', $segments));
    }

    public static function getDomain(string $key): string
    {
        return \explode('@', $key)[0];
    }

    public static function getId(string $key): Uuid
    {
        return Uuid::fromString(self::parseId($key));
    }

    public static function parseId(string $key): string
    {
        $message = \explode('@', $key, 2)[1] ?? '';
        $dotParts = \explode('.', $message);

        return \end($dotParts);
    }

    public static function getMessage(string $key): string
    {
        return \explode('@', $key)[1];
    }
}
