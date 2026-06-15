<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScoringRuleRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScoringRuleRepository::class)]
#[ORM\Table(name: 'scoring_rules')]
#[ORM\Index(columns: ['code', 'algo_version'], name: 'idx_rule_code_version')]
#[ORM\Index(columns: ['is_active'], name: 'idx_rule_active')]
class ScoringRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 20)]
    private string $algoVersion;

    #[ORM\Column]
    private int $pointsImpact;

    #[ORM\Column(nullable: true)]
    private ?float $thresholdValue = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $thresholdUnit = null;

    #[ORM\Column(nullable: true)]
    private ?int $ageMinMonths = null;

    #[ORM\Column(nullable: true)]
    private ?int $ageMaxMonths = null;

    #[ORM\Column(length: 255)]
    private string $sourceName;

    #[ORM\Column(length: 500)]
    private string $sourceUrl;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $code,
        string $label,
        string $description,
        string $algoVersion,
        int $pointsImpact,
        string $sourceName,
        string $sourceUrl,
    ) {
        $this->code = $code;
        $this->label = $label;
        $this->description = $description;
        $this->algoVersion = $algoVersion;
        $this->pointsImpact = $pointsImpact;
        $this->sourceName = $sourceName;
        $this->sourceUrl = $sourceUrl;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getAlgoVersion(): string
    {
        return $this->algoVersion;
    }

    public function getPointsImpact(): int
    {
        return $this->pointsImpact;
    }

    public function setPointsImpact(int $pointsImpact): self
    {
        $this->pointsImpact = $pointsImpact;

        return $this;
    }

    public function getThresholdValue(): ?float
    {
        return $this->thresholdValue;
    }

    public function setThresholdValue(?float $thresholdValue): self
    {
        $this->thresholdValue = $thresholdValue;

        return $this;
    }

    public function getThresholdUnit(): ?string
    {
        return $this->thresholdUnit;
    }

    public function setThresholdUnit(?string $thresholdUnit): self
    {
        $this->thresholdUnit = $thresholdUnit;

        return $this;
    }

    public function getAgeMinMonths(): ?int
    {
        return $this->ageMinMonths;
    }

    public function setAgeMinMonths(?int $ageMinMonths): self
    {
        $this->ageMinMonths = $ageMinMonths;

        return $this;
    }

    public function getAgeMaxMonths(): ?int
    {
        return $this->ageMaxMonths;
    }

    public function setAgeMaxMonths(?int $ageMaxMonths): self
    {
        $this->ageMaxMonths = $ageMaxMonths;

        return $this;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function appliesToAge(?int $babyAgeMonths): bool
    {
        if (null === $babyAgeMonths) {
            return (null === $this->ageMinMonths || 0 === $this->ageMinMonths)
                && (null === $this->ageMaxMonths || 36 === $this->ageMaxMonths);
        }
        if (null !== $this->ageMinMonths && $babyAgeMonths < $this->ageMinMonths) {
            return false;
        }
        if (null !== $this->ageMaxMonths && $babyAgeMonths > $this->ageMaxMonths) {
            return false;
        }

        return true;
    }
}
