<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ProductDto;
use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP pour l'API OpenFoodFacts v2.
 *
 * Récupère les produits par code-barres EAN avec cache Redis (7 jours).
 * Source : https://wiki.openfoodfacts.org/API
 */
final readonly class OpenFoodFactsClient
{
    private const API_URL = 'https://world.openfoodfacts.org/api/v2/product/%s.json';
    private const USER_AGENT = 'NutriPetit/1.0 (https://github.com/DamienCH33/nutripetit; contact@monavispro.fr)';
    private const API_TIMEOUT = 10;
    private const CACHE_KEY_PREFIX = 'off_product_';
    private const CACHE_TTL = 60 * 60 * 24 * 7;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.nutripetit.off_api')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {}
    /**
     * Récupère un produit par son code-barres EAN.
     *
     * @throws ProductNotFoundException          Si le produit n'existe pas dans OFF
     * @throws OpenFoodFactsUnavailableException Si l'API est indisponible
     */
    public function fetchByEan(string $ean): ProductDto
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $ean;
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var ProductDto $cached */
            $cached = $cacheItem->get();

            return $cached;
        }

        $rawData = $this->callOffApi($ean);

        $status = $rawData['status'] ?? 0;
        if (1 !== $status && '1' !== $status) {
            throw new ProductNotFoundException($ean);
        }

        $dto = ProductDto::fromOff($rawData);

        $cacheItem->set($dto);
        $cacheItem->expiresAfter(self::CACHE_TTL);

        $this->cache->save($cacheItem);

        return $dto;
    }

    /**
     * Appel HTTP brut à l'API OpenFoodFacts.
     *
     * @return array<string, mixed>
     *
     * @throws OpenFoodFactsUnavailableException
     */
    private function callOffApi(string $ean): array
    {
        $url = \sprintf(self::API_URL, urlencode($ean));

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                ],
                'timeout' => self::API_TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();

            if (500 <= $statusCode) {
                $this->logger->warning('OpenFoodFacts API returned 5xx', [
                    'ean' => $ean,
                    'status' => $statusCode,
                ]);

                throw new OpenFoodFactsUnavailableException(
                    \sprintf('Server returned HTTP %d', $statusCode),
                );
            }

            return $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('OpenFoodFacts transport error', [
                'ean' => $ean,
                'error' => $e->getMessage(),
            ]);

            throw new OpenFoodFactsUnavailableException('Network error: ' . $e->getMessage(), $e);
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('OpenFoodFacts HTTP error', [
                'ean' => $ean,
                'error' => $e->getMessage(),
            ]);

            throw new OpenFoodFactsUnavailableException('HTTP error: ' . $e->getMessage(), $e);
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('OpenFoodFacts JSON decoding error', [
                'ean' => $ean,
                'error' => $e->getMessage(),
            ]);

            throw new OpenFoodFactsUnavailableException('Invalid JSON response: ' . $e->getMessage(), $e);
        }
    }
}
