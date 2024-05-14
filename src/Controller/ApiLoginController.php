<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'app_login')]
    public function login(Request $request, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $token = $tokenStorage->getToken();

        // Récupérer le token JWT de la requête
        $jwt = $request->headers->get('Authorization');

        if (!$jwt || !str_starts_with($jwt, 'Bearer ')) {
            return new JsonResponse(['message' => 'Token manquant ou invalide'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $jwt = substr($jwt, 7); // Supprimer "Bearer " pour obtenir le token JWT


        // Create a cookie
        $cookie = Cookie::create('authToken')
            ->withValue($jwt)
            ->withExpires(time() + (7 * 24 * 60 * 60)) // 7 days
            ->withSecure(true)
            ->withHttpOnly(false) // If true, we cant get it in frontend
            ->withSameSite('strict');

        /** @var User $user */
        $user = $token->getUser();

        $response = new JsonResponse([
            'username' => $user->getLogin(),
            'user_id' => $user->getId(),
            'role' => $user->getRoles(),
        ]);

        $response->headers->setCookie($cookie);

        return $response;
    }
}
