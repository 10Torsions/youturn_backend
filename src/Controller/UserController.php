<?php

namespace App\Controller;

use App\Annotation\Secure;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users')]
class UserController extends AbstractController
{
    private $userPasswordHasher;

    /**
     * @param UserPasswordHasherInterface $userPasswordHasher - User password encryption
     */
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    /**
     * Retrieves all users based on their role
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/', name: 'user', methods: ['GET'])]
    #[Secure(roles: ["ROLE_ADMIN"])]
    public function getUsers(UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $usersList = $userRepository->findAll();

        if (empty($usersList)) {
            // If no data found, return 404
            return new JsonResponse(['message' => 'No users found'], Response::HTTP_NOT_FOUND);
        }
        $jsonUsersList = $serializer->serialize($usersList, 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUsersList, Response::HTTP_OK, [], true);
    }

    /**
     * Retrieves one user based on his role and ID.
     * @param User $user
     * @param UserRepository $userRepository,
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/{id}', name: 'detail_user', methods: ['GET'])]
    #[Secure(roles: ["ROLE_ADMIN"])]
    public function getOneUser(User $user, UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $currentUser = $userRepository->find($user);

        if (empty($currentUser)) {
            // If no data found, return 404
            return new JsonResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Turn $user object into JSON format
        $jsonUser = $serializer->serialize($currentUser, 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * Delete one user by ID.
     * @param User $user
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/{id}', name: 'delete_user', methods: ['DELETE'])]
    #[Secure(roles: ["ROLE_ADMIN"])]
    public function deleteUser(User $user, EntityManagerInterface $em): JsonResponse
    {

        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Create an User using the following structure 
     * 
     * {
     *  "login" : "login",
     *  "password" : "password",
     *  "roles": ["ROLE_TEST"]
     * }
     * Use the UserPasswordHasherInterface to hash the password
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/', name: 'create_user', methods: ['POST'])]
    #[Secure(roles: ["ROLE_ADMIN"])]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        // Deserialize $request->getcontent
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // Check if user data is conform
        $errors = $validator->validate($user);

        // If validation error, ERROR 400 is returned
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        } else {

            // Data is ok
            // Hash the password before persisting
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $user->getPassword()));

            // Persist and upgrade user
            $em->persist($user);
            $em->flush();

            // Serialize new User Object for response
            $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);

            $location = $urlGenerator->generate('detail_user', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location]);
        }
    }

    /**
     * PUT an existing user
     * {
     *  "login" : "newLogin",
     *  "password" : "newPassword",
     *  "roles": ["ROLE_NEW"]
     * }
     * 
     * @param Request $request
     * @param User $currentUser
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/{id}', name: 'update_user', methods: ['PUT'])]
    #[Secure(roles: ["ROLE_ADMIN"])]
    public function updateUser(Request $request, User $currentUser, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        /* The request body is deserialized into an existing User object ($currentUser) using the OBJECT_TO_POPULATE option.
        * This ensures that the existing user's properties are updated with the new data, rather than creating a new object
        */
        $updatedUser = $serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]
        );

        /* The updated user object is validated using the ValidatorInterface to ensure the data is valid.
        *   If any errors are found, a JSON response with the errors is returned with a HTTP_BAD_REQUEST status
        */
        $errors = $validator->validate($updatedUser);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // Check if a new password has been provided
        $newPassword = $updatedUser->getPassword();
        if (!empty($newPassword)) {
            // Encode the new password
            $encodedPassword = $this->userPasswordHasher->hashPassword($updatedUser, $newPassword);
            $updatedUser->setPassword($encodedPassword);
        } else {
            // If no new password provided, keep the current password
            $updatedUser->setPassword($currentUser->getPassword());
        }

        // The updated user object is persisted to the database using EntityManagerInterface
        $em->persist($updatedUser);
        $em->flush();

        // A JsonResponse with a HTTP_NO_CONTENT status code 204 is returned, indicating successful update without any content in the response body.
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
