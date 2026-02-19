<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Entity;

use ChamberOrchestra\DoctrineClockBundle\Contracts\Entity\TimestampCreateInterface;
use ChamberOrchestra\DoctrineClockBundle\Entity\TimestampCreateTrait;
use ChamberOrchestra\DoctrineExtensionsBundle\Entity\IdTrait;
use ChamberOrchestra\TranslationBundle\Repository\TranslationRepository;
use ChamberOrchestra\TranslationBundle\Utils\TranslationHelper;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
#[ORM\ChangeTrackingPolicy(value: 'DEFERRED_EXPLICIT')]
class Translation implements TimestampCreateInterface
{
    use IdTrait;
    use TimestampCreateTrait;

    #[ORM\Column(type: 'string', length: 128, nullable: false)]
    private string $domain;
    #[ORM\Column(type: 'string', length: 128, nullable: false)]
    private string $message;
    #[ORM\Column(type: 'text', nullable: false)]
    private string $value;
    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $context;
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $exported = false;

    public function __construct(Uuid $id, string $domain, string $message, string $value, ?string $context = null)
    {
        $this->id = $id;
        $this->domain = $domain;
        $this->message = $message;
        $this->value = $value;
        $this->context = $context;
    }

    public static function create(string $key, string $value, ?string $context = null): self
    {
        return new self(
            TranslationHelper::getId($key),
            TranslationHelper::getDomain($key),
            TranslationHelper::getMessage($key),
            $value,
            $context,
        );
    }

    public function update(string $value): void
    {
        $this->value = $value;
    }

    public function export(): void
    {
        $this->exported = true;
    }

    public function markAsNeedToExport(): void
    {
        $this->exported = false;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function isExported(): bool
    {
        return $this->exported;
    }
}
