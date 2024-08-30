<?php

namespace App\Controller;

use App\Annotation\Secure;
use App\Entity\Activity;
use App\Entity\Scenario;
use App\Repository\ActivityRepository;
use App\Repository\UserRepository;
use App\Service\CodeGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/activity')]
class ActivityController extends AbstractController
{
    private $activityRepository;
    private $serializer;
    private $em;
    private $validator;

    public function __construct(
        ActivityRepository $activityRepository, 
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        ){
            $this->activityRepository = $activityRepository;
            $this->serializer = $serializer;
            $this->em = $em;
            $this->validator = $validator;
    }

    #[Route('/', name: 'activity', methods: ['GET'])]
    #[Secure(roles: ["ROLE_ADMIN", "ROLE_GAMEMASTER"])]
    public function getActivitiesList(): JsonResponse
    {
        $activitiesList = $this->activityRepository->findAll();
        $jsonActivitiesList = $this->serializer->serialize($activitiesList, 'json', ['groups' => 'getActivityNameAndId']);
        return new JsonResponse($jsonActivitiesList, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'detail_activity', methods: ['GET'])]
    public function getDetailActivity(Activity $activity): JsonResponse
    {
        // If no activity ParamConverter will throw an Exception
        // Turn $activity object into JSON format
        $jsonActivity = $this->serializer->serialize($activity, 'json', ['groups' => 'getActivity']);
        return new JsonResponse($jsonActivity, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/stands', name: 'activity_stands', methods: ['GET'])]
    public function getActivityStands(Activity $activity): JsonResponse
    {
        // Serialization logic here
        $stands = $activity->getStands();
        $jsonStands = $this->serializer->serialize($stands, 'json');
        return new JsonResponse($jsonStands, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/teams', name: 'activity_teams', methods: ['GET'])]
    public function getActivityTeams(Activity $activity): JsonResponse
    {
        $teams = $activity->getTeams();
        $jsonTeams = $this->serializer->serialize($teams, 'json');
        return new JsonResponse($jsonTeams, Response::HTTP_OK, [], true);
    }

    /**
     * Gets an activity by pin code
     * @param string $routePrefix API endpoint for role
     * @param string $pincode Code to check
     * @return JsonResponse
     */
    #[Route(['/{routePrefix}/code/{pincode}'], name: 'activity_pincode', methods: ['GET'])]
    public function getActivityAndRoleByPinCode(string $routePrefix, string $pincode): JsonResponse
    {
        switch ($routePrefix) {
            case 'participant':
                $result = $this->activityRepository->findBy(['participantCode' => $pincode]);
                $role = 'participant';
                break;
            case 'animator':
                $result = $this->activityRepository->findBy(['animatorCode' => $pincode]);
                $role = 'animator';
                break;
            default:
                // No route match
                return new JsonResponse(['message' => 'Route non reconnue'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$result) {
            return new JsonResponse(['message' => 'Activité non trouvée'], JsonResponse::HTTP_NOT_FOUND);
        }
        $role = strtoupper($role);
        $data = $this->serializer->serialize([
            'activity_id' => $result[0]->getId(),
            'role' => 'ROLE_' . $role
        ], 'json');

        return new JsonResponse($data, JsonResponse::HTTP_OK, [], true);
    }

    /**
     * @return JsonResponse
     */
    #[Route('/delete/{id}', name: 'delete_activity', methods: ['DELETE'])]
    #[Secure(roles: ["ROLE_ADMIN", "ROLE_GAMEMASTER"])]
    public function deleteActivity(Activity $activity): JsonResponse
    {
        $this->em->getConnection()->beginTransaction(); // Start transaction

        try {

            $this->em->remove($activity);
            $this->em->flush(); // Persist changes

            $this->em->getConnection()->commit(); // Commit transaction

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack(); // Roll back on error

            // Return a JsonResponse indicating an error
            return new JsonResponse([
                'error' => 'An unexpected error occurred.',
                'message' => $e->getMessage() // Optionally include the exception message for debugging
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create an Activity (and empty Scenario) using the following structure 
     * 
     * {
     *  "name" : "activityName",
     *  "user" : 10 (needed for the moment, see AuthenticationService)
     * }
     * 
     * @param Request $request
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $em
     * @param CodeGeneratorService $codeGenerator
     * @return JsonResponse
     */
    #[Route('/', name: 'create_activity', methods: ['POST'])]
    #[Secure(roles: ["ROLE_ADMIN", "ROLE_GAMEMASTER"])]
    public function createActivity(Request $request, UserRepository $userRepository, CodeGeneratorService $codeGenerator): JsonResponse
    {
        // Create new activity object with data provided
        $newActivity = $this->serializer->deserialize($request->getContent(), Activity::class, 'json');

        // Decode request content
        $requestData = json_decode($request->getContent(), true);

        // Setting User and Activity to the Activity
        if (!empty($requestData['user'])) {
            $user = $userRepository->find($requestData['user']);
            if (!$user) {
                return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }
            // Associate found User with the new Activity
            $newActivity->setUser($user);
        }

        // Générer les codes pour les participants et les animateurs
        $participantCode = $codeGenerator->generateUniqueCode('participantCode');
        $animatorCode = $codeGenerator->generateUniqueCode('animatorCode');
        $newActivity->setParticipantCode($participantCode);
        $newActivity->setAnimatorCode($animatorCode);

        // Validate the Activity entity before flush
        $errors = $this->validator->validate($newActivity);
        if (count($errors) > 0) {
            return new JsonResponse($this->serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // If everything is ok, generate new empty Scenario
        $scenario = new Scenario();
        $scenario->setActivity($newActivity);
        // Persist and flush new Activity
        $this->em->persist($newActivity);
        $this->em->persist($scenario);

        $this->em->flush();

        // Return a JSON response with the ID of the newly created Activity
        return new JsonResponse(['activity_id' => $newActivity->getId()], JsonResponse::HTTP_CREATED);
    }

    /** PUT an existing activity
     * {
     * "name" : "new Activity name",
     * "global_duration" : 4500
     * "activity_id" : 27
     * }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/{id}', name: 'update_activity', methods: ['PUT'])]
    #[Secure(roles: ["ROLE_ADMIN", "ROLE_GAMEMASTER"])]
    public function updateActivity(Request $request, Activity $activity): JsonResponse
    {

        // Deserialization and Update Activity
        $this->serializer->deserialize($request->getContent(), Activity::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $activity]);

        /* The updated activity object is validated using the ValidatorInterface to ensure the data is valid.
        *  If any errors are found, a JSON response with the errors is returned with a HTTP_BAD_REQUEST status*/
        $errors = $this->validator->validate($activity);

        if ($errors->count() > 0) {
            // Srialize data
            return new JsonResponse($this->serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $this->em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
