<?php

declare(strict_types=1);

namespace App\Service\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Levée quand l'API OpenFoodFacts est indisponible (timeout, 5xx, réseau).
 */
final class OpenFoodFactsUnavailableException extends HttpException
{
    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct(
            statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
            message: \sprintf('OpenFoodFacts service is currently unavailable: %s', $reason),
            previous: $previous,
        );
    }
}
