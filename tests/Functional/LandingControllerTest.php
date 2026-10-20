<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LandingControllerTest extends WebTestCase
{
    public function testHomeShowsTheVerdictPromiseAndTheHealthWarning(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'vous pouvez le lui donner');
        self::assertSelectorExists('form[action="/essayer"] input[name="ean"]');
        self::assertSelectorTextContains('body > footer', "n'est pas un professionnel de santé");
        self::assertSelectorTextContains('body > footer', 'appelez le 15');
    }

    public function testTryRedirectsToTheProductPageOfTheApp(): void
    {
        $client = self::createClient();
        $client->request('GET', '/essayer', ['ean' => ' 3701293900233 ']);

        self::assertResponseRedirects('/app/scan/3701293900233', 303);
    }

    #[DataProvider('invalidCodes')]
    public function testTryFallsBackToManualEntryOnAnInvalidCode(string $ean): void
    {
        $client = self::createClient();
        $client->request('GET', '/essayer', ['ean' => $ean]);

        self::assertResponseRedirects('/app/saisie-manuelle', 303);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCodes(): iterable
    {
        yield 'vide' => [''];
        yield 'trop court' => ['1234'];
        yield 'url' => ['//evil.example/'];
    }

    #[DataProvider('pages')]
    public function testEveryPublicPageRendersWithTheNewHeaderAndFooter(string $url): void
    {
        $client = self::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body > header .v-logo');
        self::assertSelectorTextContains('body > footer', "n'est pas un professionnel de santé");
    }

    /** @return iterable<string, array{string}> */
    public static function pages(): iterable
    {
        yield 'à propos' => ['/a-propos'];
        yield 'confidentialité' => ['/confidentialite'];
        yield 'mentions légales' => ['/mentions-legales'];
    }
}
