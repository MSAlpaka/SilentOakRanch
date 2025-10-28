<?php

namespace App\Wordpress\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class WebhookTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        #[Autowire(env: 'WORDPRESS_WEBHOOK_TOKEN')]
        private readonly string $webhookToken,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if ($request->getMethod() === Request::METHOD_OPTIONS) {
            return false;
        }

        return str_starts_with($request->getPathInfo(), '/api/wp');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorizationHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new AuthenticationException('Invalid authorization header.');
        }

        $token = trim(substr($authorizationHeader, 7));

        if ($token === '' || $this->webhookToken === '') {
            throw new AuthenticationException('Webhook token missing.');
        }

        if (!hash_equals($this->webhookToken, $token)) {
            throw new AuthenticationException('Invalid webhook token.');
        }

        $userBadge = new UserBadge('wordpress-webhook', static function (): InMemoryUser {
            return new InMemoryUser('wordpress-webhook', '', ['ROLE_WORDPRESS_WEBHOOK']);
        });

        return new SelfValidatingPassport($userBadge);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'ok' => false,
            'error' => 'Unauthorized',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
