<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Product;
use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;
use App\Service\OpenFoodFactsClient;
use App\Service\Product\ProductPreviewBuilder;
use App\Service\Scoring\BabyProductDetector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests bout-en-bout de la route app_pwa_scan.
 * EAN-13 valides : 3017620422003 et 4006381333931.
 *
 * Prérequis : dama/doctrine-test-bundle activé (rollback entre tests) +
 * dg/bypass-finals (mock de classes finales). IP distinctes par test pour
 * isoler le compteur du rate limiter.
 */
final class ScannerControllerTest extends WebTestCase
{
    private const VALID_EAN = '3017620422003';
    private const VALID_EAN_2 = '4006381333931';

    public function testReturns404WhenProductUnknown(): void
    {
        $client = static::createClient();
        $this->mockOffClient(new ProductNotFoundException(self::VALID_EAN));

        $client->request('GET', '/app/scan/' . self::VALID_EAN, [], [], ['REMOTE_ADDR' => '203.0.113.2']);

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testReturns503WhenOffUnavailable(): void
    {
        $client = static::createClient();
        $this->mockOffClient(new OpenFoodFactsUnavailableException('timeout'));

        $client->request('GET', '/app/scan/' . self::VALID_EAN, [], [], ['REMOTE_ADDR' => '203.0.113.3']);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $client->getResponse()->getStatusCode());
    }

    public function testReturns200WithNoticeForNonBabyProduct(): void
    {
        $client = static::createClient();
        $this->persistProduct(self::VALID_EAN, 'Produit adulte');

        $detector = $this->createStub(BabyProductDetector::class);
        $detector->method('isBabyProduct')->willReturn(false);
        static::getContainer()->set(BabyProductDetector::class, $detector);

        $client->request('GET', '/app/scan/' . self::VALID_EAN, [], [], ['REMOTE_ADDR' => '203.0.113.4']);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('0-3 ans', (string) $client->getResponse()->getContent());
    }

    public function testKnownBabyProductReturns200(): void
    {
        $client = static::createClient();
        $this->persistProduct(self::VALID_EAN_2, 'Petit pot carottes');

        $detector = $this->createStub(BabyProductDetector::class);
        $detector->method('isBabyProduct')->willReturn(true);
        static::getContainer()->set(BabyProductDetector::class, $detector);

        $builder = $this->createStub(ProductPreviewBuilder::class);
        $builder->method('build')->willReturn($this->minimalViewData('Petit pot carottes', self::VALID_EAN_2));
        static::getContainer()->set(ProductPreviewBuilder::class, $builder);

        $client->request('GET', '/app/scan/' . self::VALID_EAN_2, [], [], ['REMOTE_ADDR' => '203.0.113.5']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Petit pot carottes');
    }

    public function testScanRateLimiterBlocksBeyondItsLimit(): void
    {
        self::bootKernel();

        /** @var \Symfony\Component\RateLimiter\RateLimiterFactory $factory */
        $factory = static::getContainer()->get('limiter.scan');
        $limiter = $factory->create('rate-limit-test-' . uniqid());

        $blocked = false;
        for ($i = 0; $i < 100; ++$i) {
            if (!$limiter->consume(1)->isAccepted()) {
                $blocked = true;
                break;
            }
        }

        self::assertTrue($blocked, 'Le limiter "scan" doit refuser au-delà de sa limite.');
    }

    private function mockOffClient(\Throwable $exception): void
    {
        $off = $this->createStub(OpenFoodFactsClient::class);
        $off->method('fetchByEan')->willThrowException($exception);
        static::getContainer()->set(OpenFoodFactsClient::class, $off);
    }

    private function persistProduct(string $ean, string $name): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new Product($ean, $name));
        $em->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalViewData(string $name, string $ean): array
    {
        return [
            'product' => ['ean' => $ean, 'name' => $name, 'brand' => 'Marque', 'image_url' => null],
            'babyAgeMonths' => null,
            'finalScore' => 100,
            'level' => 'ideal',
            'algoVersion' => '1.0.0',
            'isInfantFormula' => false,
            'scoresByAge' => [],
            'criticalAlert' => null,
            'appliedRules' => [],
            'nutrients' => [],
            'environment' => ['origin' => null, 'bio_certified' => false, 'palm_oil_free' => true, 'packaging' => null],
            'uniqueSources' => [],
            'minAgeMonths' => null,
            'additives' => [],
            'carbonFootprint' => null,
        ];
    }
}
