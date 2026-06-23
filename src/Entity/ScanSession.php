<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScanSessionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ScanSessionRepository::class)]
#[ORM\Table(name: 'scan_sessions')]
#[ORM\Index(columns: ['last_active_at'], name: 'idx_session_last_active')]
class ScanSession
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 64, unique: true)]
    private string $cookieToken;

    #[ORM\Column(nullable: true)]
    private ?int $babyAgeMonths = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $lastActiveAt;

    public function __construct(?string $userAgent = null)
    {
        $this->id = Uuid::v7();
        $this->cookieToken = bin2hex(random_bytes(32));
        $this->userAgent = $userAgent;
        $this->createdAt = new DateTimeImmutable();
        $this->lastActiveAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCookieToken(): string
    {
        return $this->cookieToken;
    }

    public function getBabyAgeMonths(): ?int
    {
        return $this->babyAgeMonths;
    }

    public function setBabyAgeMonths(?int $babyAgeMonths): self
    {
        $this->babyAgeMonths = $babyAgeMonths;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActiveAt(): DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function touch(): self
    {
        $this->lastActiveAt = new DateTimeImmutable();

        return $this;
    }
}
