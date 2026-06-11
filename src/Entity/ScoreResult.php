<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScoreResultRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ScoreResultRepository::class)]
#[ORM\Table(name: 'score_results')]
#[ORM\UniqueConstraint(
    name: 'uniq_session_product',
    columns: ['scan_session_id', 'product_ean']
)]
#[ORM\Index(columns: ['product_ean'], name: 'idx_score_product')]
#[ORM\Index(columns: ['calculated_at'], name: 'idx_score_calculated_at')]
class ScoreResult
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_ean', referencedColumnName: 'ean', nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: ScanSession::class)]
    #[ORM\JoinColumn(name: 'scan_session_id', referencedColumnName: 'id', nullable: true)]
    private ?ScanSession $scanSession = null;

    #[ORM\Column]
    private int $finalScore;

    #[ORM\Column(length: 20)]
    private string $level;

    #[ORM\Column]
    private int $scanCount = 1;

    #[ORM\Column]
    private DateTimeImmutable $firstScannedAt;

    #[ORM\Column]
    private DateTimeImmutable $lastScannedAt;

    /**
     * @var list<array{rule_code: string, rule_label: string, points: int, reason: string, source_name: string, source_url: string}>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $appliedRules = [];

    #[ORM\Column(length: 20)]
    private string $algoVersion;

    #[ORM\Column(nullable: true)]
    private ?int $babyAgeMonths = null;

    #[ORM\Column]
    private DateTimeImmutable $calculatedAt;

    public function __construct(
        Product $product,
        int $finalScore,
        string $level,
        string $algoVersion,
        ?int $babyAgeMonths = null,
        ?ScanSession $scanSession = null,
    ) {
        $now = new DateTimeImmutable();

        $this->id = Uuid::v7();
        $this->product = $product;
        $this->finalScore = max(0, min(100, $finalScore));
        $this->level = $level;
        $this->algoVersion = $algoVersion;
        $this->babyAgeMonths = $babyAgeMonths;
        $this->scanSession = $scanSession;

        $this->calculatedAt = $now;
        $this->firstScannedAt = $now;
        $this->lastScannedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getScanSession(): ?ScanSession
    {
        return $this->scanSession;
    }

    public function getFinalScore(): int
    {
        return $this->finalScore;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    /** @return list<array{rule_code: string, rule_label: string, points: int, reason: string, source_name: string, source_url: string}> */
    public function getAppliedRules(): array
    {
        return $this->appliedRules;
    }

    /** @param list<array{rule_code: string, rule_label: string, points: int, reason: string, source_name: string, source_url: string}> $appliedRules */
    public function setAppliedRules(array $appliedRules): self
    {
        $this->appliedRules = $appliedRules;

        return $this;
    }

    public function getAlgoVersion(): string
    {
        return $this->algoVersion;
    }

    public function getBabyAgeMonths(): ?int
    {
        return $this->babyAgeMonths;
    }

    public function getCalculatedAt(): DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function getScanCount(): int
    {
        return $this->scanCount;
    }

    public function getFirstScannedAt(): DateTimeImmutable
    {
        return $this->firstScannedAt;
    }

    public function getLastScannedAt(): DateTimeImmutable
    {
        return $this->lastScannedAt;
    }

    /**
     * @param list<array<string, mixed>> $appliedRules
     */
    public function refresh(
        int $finalScore,
        string $level,
        array $appliedRules,
        ?int $babyAgeMonths,
    ): void {
        $this->finalScore = max(0, min(100, $finalScore));
        $this->level = $level;
        $this->appliedRules = $appliedRules;
        $this->babyAgeMonths = $babyAgeMonths;

        ++$this->scanCount;

        $now = new DateTimeImmutable();

        $this->calculatedAt = $now;
        $this->lastScannedAt = $now;
    }
}
