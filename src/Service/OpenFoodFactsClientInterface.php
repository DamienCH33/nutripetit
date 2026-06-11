<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ProductDto;
use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;

interface OpenFoodFactsClientInterface
{
    /**
     * @throws ProductNotFoundException
     * @throws OpenFoodFactsUnavailableException
     */
    public function fetchByEan(string $ean): ProductDto;
}
