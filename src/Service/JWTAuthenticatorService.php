<?php

namespace App\Service;

use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class JWTAuthenticatorService
{
    private $jwtEncoder;

    public function __construct(JWTEncoderInterface $jwtEncoder)
    {
        $this->jwtEncoder = $jwtEncoder;
    }

    /**
     * Authenticates a user based on JWT and CSRF tokens, and checks for required roles.
     *
     * @param Request $request
     * @param array $requiredRoles Roles required for accessing the route. Default is ['ROLE_ADMIN'].
     * @throws AccessDeniedException If the user does not have the required roles or tokens are missing/mismatched.
     * @throws AuthenticationException If the JWT token is invalid.
     */
    public function authenticate(Request $request, array $requiredRoles = ['ROLE_ADMIN'])
    {

        if (!$request->cookies || !$request->cookies->get('access_token')) {
            throw new AccessDeniedException('Missing token in cookie');
        }

        // Retrieve the JWT cookie from the request
        $accessToken  = $request->cookies->get('access_token');

        if (!$request->headers || !$request->headers->get('x-xsrf-token')) {
            throw new AccessDeniedException('Missing XSRF token in headers');
        }

        // Récupérer le token CSRF dans les entêtes de la requete
        $csrfToken = $request->headers->get('x-xsrf-token');

        try {
            // Decode the JWT
            $payload = $this->jwtEncoder->decode($accessToken);

            // Compare the CSRF token from the header with the one in the JWT payload
            if ($payload['csrf'] !== $csrfToken) {
                throw new AccessDeniedException('CSRF token mismatch');
            }

            // Verify user Roles
            $userRoles = $payload['roles'];
            foreach ($requiredRoles as $role) {
                if (in_array($role, $userRoles)) {
                    return;
                }
            }

            throw new AccessDeniedException('Accès non autorisé pour ce rôle.');
        } catch (\Exception $e) {
            throw new AuthenticationException('Token invalide.');
        }
    }
}