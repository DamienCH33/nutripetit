<?php

declare(strict_types=1);

namespace App\Service\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Levée quand un EAN scanné n'existe pas dans la base OpenFoodFacts.
 */
final class ProductNotFoundException extends HttpException
{
    public function __construct(
        private readonly string $ean,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            statusCode: Response::HTTP_NOT_FOUND,
            message: \sprintf('Product with EAN %s not found in OpenFoodFacts', $ean),
            previous: $previous,
        );
    }

    public function getEan(): string
    {
        return $this->ean;
    }
}
