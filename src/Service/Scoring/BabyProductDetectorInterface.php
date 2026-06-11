<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Product;

interface BabyProductDetectorInterface
{
    public function isBabyProduct(Product $product): bool;
}
