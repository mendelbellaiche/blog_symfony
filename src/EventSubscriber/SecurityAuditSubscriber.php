<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $auditLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $message = $event->getAuthenticator() instanceof RememberMeAuthenticator
            ? 'Connexion automatique (se souvenir de moi)'
            : 'Connexion réussie';

        $this->auditLogger->info($message, [
            'user' => $event->getUser()->getUserIdentifier(),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $this->auditLogger->warning('Échec de connexion', [
            'email' => $event->getRequest()->getPayload()->get('_username'),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $this->auditLogger->info('Déconnexion', [
            'user' => $event->getToken()?->getUserIdentifier(),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }
}
