<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(length: 13)]
    private string $ean;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ingredientsRaw = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $nutriments = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $allergens = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $additives = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $offRawData = [];

    #[ORM\Column]
    private DateTimeImmutable $fetchedAt;

    public function __construct(string $ean, string $name)
    {
        $this->ean = $ean;
        $this->name = $name;
        $this->fetchedAt = new DateTimeImmutable();
    }

    public function getEan(): string
    {
        return $this->ean;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getIngredientsRaw(): ?string
    {
        return $this->ingredientsRaw;
    }

    public function setIngredientsRaw(?string $ingredientsRaw): self
    {
        $this->ingredientsRaw = $ingredientsRaw;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getNutriments(): array
    {
        return $this->nutriments;
    }

    /** @param array<string, mixed> $nutriments */
    public function setNutriments(array $nutriments): self
    {
        $this->nutriments = $nutriments;

        return $this;
    }

    /** @return list<string> */
    public function getAllergens(): array
    {
        return $this->allergens;
    }

    /** @param list<string> $allergens */
    public function setAllergens(array $allergens): self
    {
        $this->allergens = $allergens;

        return $this;
    }

    /** @return list<string> */
    public function getAdditives(): array
    {
        return $this->additives;
    }

    /** @param list<string> $additives */
    public function setAdditives(array $additives): self
    {
        $this->additives = $additives;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getOffRawData(): array
    {
        return $this->offRawData;
    }

    /** @param array<string, mixed> $offRawData */
    public function setOffRawData(array $offRawData): self
    {
        $this->offRawData = $offRawData;

        return $this;
    }

    public function getFetchedAt(): DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function refreshFetchedAt(): self
    {
        $this->fetchedAt = new DateTimeImmutable();

        return $this;
    }
}
