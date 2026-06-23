<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Headers de sécurité sur toutes les réponses HTTP.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=()');
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
                . "img-src 'self' https://images.openfoodfacts.org https://static.openfoodfacts.org data:; "
                . "script-src 'self' 'unsafe-inline' data:; "
                . "style-src 'self' 'unsafe-inline'; "
                . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        );
    }
}
