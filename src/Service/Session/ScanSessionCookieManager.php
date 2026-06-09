<?php

declare(strict_types=1);

namespace App\Service\Session;

use App\Entity\ScanSession;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ScanSessionCookieManager
{
    /**
     * Pose le cookie de session sur la réponse si le cookie reçu
     * ne correspond pas déjà à la session résolue (absent ou périmé).
     */
    public function ensureScanSessionCookie(
        Request $request,
        Response $response,
        ScanSession $scanSession,
    ): Response {
        $sessionId = (string) $scanSession->getId();

        if ($sessionId === $request->cookies->get(ScanSessionManager::SESSION_COOKIE_NAME)) {
            return $response;
        }

        $response->headers->setCookie(
            Cookie::create(
                name: ScanSessionManager::SESSION_COOKIE_NAME,
                value: $sessionId,
                expire: time() + (ScanSessionManager::SESSION_COOKIE_TTL_DAYS * 86400),
                path: '/',
                secure: true,
                httpOnly: true,
                sameSite: 'lax',
            ),
        );

        return $response;
    }
}
