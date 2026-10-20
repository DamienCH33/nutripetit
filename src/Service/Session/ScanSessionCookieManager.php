<?php

declare(strict_types=1);

namespace App\Service\Session;

use App\Entity\ScanSession;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ScanSessionCookieManager
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment = 'prod',
    ) {
    }

    /**
     * Pose le cookie de session sur la réponse si le cookie reçu
     * ne correspond pas déjà au jeton de la session résolue (absent ou périmé).
     */
    public function ensureScanSessionCookie(
        Request $request,
        Response $response,
        ScanSession $scanSession,
    ): Response {
        $token = $scanSession->getCookieToken();

        if ($token === $request->cookies->get(ScanSessionManager::SESSION_COOKIE_NAME)) {
            return $response;
        }

        // En prod, l'API est jointe en http interne derrière Caddy : isSecure()
        // peut être faux alors que le navigateur est en HTTPS. Le cookie doit
        // quand même être Secure. Front et API partagent le même domaine :
        // Lax suffit (None n'était utile qu'avec deux domaines).
        $secure = $request->isSecure() || 'prod' === $this->environment;

        $response->headers->setCookie(
            Cookie::create(
                name: ScanSessionManager::SESSION_COOKIE_NAME,
                value: $token,
                expire: time() + (ScanSessionManager::SESSION_COOKIE_TTL_DAYS * 86400),
                path: '/',
                secure: $secure,
                httpOnly: true,
                sameSite: Cookie::SAMESITE_LAX,
            ),
        );

        return $response;
    }
}
