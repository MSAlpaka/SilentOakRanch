<?php

namespace App\RanchBooking\Security;

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

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        #[Autowire(env: 'RANCH_API_KEY')]
        private readonly string $apiKey
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if ($request->getMethod() === Request::METHOD_OPTIONS) {
            return false;
        }

        return str_starts_with($request->getPathInfo(), '/api/bookings');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorizationHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new AuthenticationException('Invalid authorization header.');
        }

        $token = trim(substr($authorizationHeader, 7));

        if ($token === '' || $this->apiKey === '') {
            throw new AuthenticationException('API key missing.');
        }

        if (!hash_equals($this->apiKey, $token)) {
            throw new AuthenticationException('Invalid API key.');
        }

        $userBadge = new UserBadge('booking-api', function (): InMemoryUser {
            return new InMemoryUser('booking-api', '', ['ROLE_RANCH_BOOKING']);
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
