<?php

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Entity\User;
use App\Service\CodeGeneratorService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ActivityTest extends WebTestCase
{

    private $client;
    private $em;
    private $jwtManager;
    private $codeGenerator;
    private $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->jwtManager = $this->client->getContainer()->get(JWTTokenManagerInterface::class);
        $this->codeGenerator = $this->client->getContainer()->get(CodeGeneratorService::class);
        $this->passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);
    }

    /**
     * Creates a test User
     * 
     * @param string $userName Optional, default is "User Test"
     * @param string $userRole Optional, default is "GAMEMASTER"
     * @return User
     */
    private function createTestUser($userName = "User Test", $userRole = "GAMEMASTER"): User
    {
        // Create a new test User
        $user = new User();
        $user->setLogin($userName)
            ->setPassword('testpass')
            ->setRoles(['ROLE_' . $userRole]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Authenticates a user and creates a CSRF token.
     * Also creates a cookie with the access token, user information, and CSRF token.
     * 
     * @param string $userToAuthenticateId
     * @return string The CSRF token
     */
    private function authenticateUser($userToAuthenticateId): string
    {
        $user = $this->em->find(User::class, $userToAuthenticateId);

        // Generate CSRF token
        $csrfToken = bin2hex(random_bytes(32));

        // Generate access token JWT with CSRF and user details
        $tokenData = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'csrf' => $csrfToken,
            'roles' => $user->getRoles(),
        ];
        $token = $this->jwtManager->createFromPayload($user, $tokenData);

        // Add cookie to headers
        $this->client->getCookieJar()->set(new Cookie('access_token', $token));

        return $csrfToken;
    }

    /**
     * Creates a test activity.
     * 
     * @param string $activityName Optional, default is "Test Activity"
     * @return Activity
     */
    private function createTestActivity($activityName = "Test Activity"): Activity
    {
        $user = $this->createTestUser('User TestActivity creation');

        // Generate codes for participants and animators
        $participantCode = $this->codeGenerator->generateUniqueCode('participantCode');
        $animatorCode = $this->codeGenerator->generateUniqueCode('animatorCode');

        // Create a new activity
        $activity = new Activity();
        $activity->setName($activityName)
            ->setParticipantCode($participantCode)
            ->setAnimatorCode($animatorCode)
            ->setUser($user);

        $this->em->persist($activity);
        $this->em->flush();

        return $activity;
    }

    /**
     * Tests getting the details of an activity.
     * @return void
     */
    public function testGetDetailActivity(): void
    {
        $activity = $this->createTestactivity('Activity details test');

        // Send GET request to retrieve activity details
        $this->client->request('GET', '/activity/' . $activity->getId());

        // Verify response code
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Decode response
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($activity->getId(), $responseData['id']);
        $this->assertEquals($activity->getName(), $responseData['name']);
        $this->assertEquals($activity->getActivityDate(), $responseData['activity_date']);
        $this->assertEquals($activity->getCreatedAt()->format('c'), $responseData['createdAt']);
        $this->assertEquals($activity->getNbTeams(), $responseData['nb_teams']);
        $this->assertEquals($activity->getGlobalDuration(), $responseData['global_duration']);
        $this->assertEquals($activity->getRotationDuration(), $responseData['rotation_duration']);
        $this->assertEquals($activity->getStandDuration(), $responseData['stand_duration']);
        $this->assertEquals($activity->getParticipantCode(), $responseData['participantCode']);
        $this->assertEquals($activity->getAnimatorCode(), $responseData['animatorCode']);

        // Vérify stands
        if (!empty($responseData['stands'])) {
            foreach ($activity->getStands() as $index => $stand) {
                $this->assertEquals($stand->getId(), $responseData['stands'][$index]['id']);
                $this->assertEquals($stand->getName(), $responseData['stands'][$index]['name']);
                $this->assertEquals($stand->getNbTeamsOnStand(), $responseData['stands'][$index]['nbTeamsOnStand']);
            }
        } else {
            $this->assertEmpty($responseData['stands']);
        }

        // Vérify teams
        if (!empty($responseData['teams'])) {
            foreach ($activity->getTeams() as $index => $team) {
                $this->assertEquals($team->getId(), $responseData['teams'][$index]['teamId']);
                $this->assertEquals($team->getName(), $responseData['teams'][$index]['teamName']);
            }
        } else {
            $this->assertEmpty($responseData['teams']);
        }
    }

    /**
     * Tests creating an activity.
     * @return void
     */
    public function testIfCreateActivityIsSuccessful(): void
    {
        $user = $this->createTestUser('User Create Activity');

        // Authenticate the user
        $csrfToken = $this->authenticateUser($user->getId());

        // Mock data for creating an activity
        $data = [
            'name' => 'Test Activity',
            'user' => $user->getId(),
        ];

        $jsonData = json_encode($data);

        // Send POST request to create activity
        $this->client->request(
            'POST',
            '/activity/', // POST Activity Path
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_XSRF_TOKEN' => $csrfToken,
            ],
            $jsonData
        );

        // Assert response status code
        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        // Decode the response content
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);

        // Assert that the response contains the activity ID
        $this->assertArrayHasKey('activity_id', $responseContent);
    }

    /**
     * Tests updating an activity.
     * @return void
     */
    public function testIfUpdateActivityIsSuccessfull(): void
    {

        // Create test User
        $user = $this->createTestUser('User UPDATE Activity');

        // Authenticate the user
        $csrfToken = $this->authenticateUser($user->getId());

        // Create test activity
        $activity = $this->createTestactivity('Activity UPDATE test');

        $createdActivityId = $activity->getId();

        // Mock data for updating the activity
        $data = [
            'name' => 'Updated Activity Name',
            'global_duration' => 4500,
        ];

        $jsonData = json_encode($data);

        // Send PUT request to update the activity
        $this->client->request(
            'PUT',
            '/activity/' . $createdActivityId,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_XSRF_TOKEN' => $csrfToken,
            ],
            $jsonData
        );

        // Verify response code
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // Verify that the activity has been updated in the database
        $updatedActivity = $this->em->getRepository(Activity::class)->find($createdActivityId);
        $this->assertEquals('Updated Activity Name', $updatedActivity->getName());
        $this->assertEquals(4500, $updatedActivity->getGlobalDuration());
    }

    /**
     * Tests deleting an activity.
     * @return void
     */
    public function testIfDeleteActivityIsSuccessfull(): void
    {
        // Create test User
        $user = $this->createTestUser('User DELETE Activity');

        // Authenticate the user
        $csrfToken = $this->authenticateUser($user->getId());

        // Create Test Activity
        $activity = $this->createTestactivity('Activity DELETE test');

        $activityId = $activity->getId();

        // Send DELETE request to delete the activity
        $this->client->request(
            'DELETE',
            '/activity/delete/' . $activityId,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_XSRF_TOKEN' => $csrfToken,
            ]
        );

        // Verify response code
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // Verify that the activity has been deleted from the database
        $deletedActivity = $this->em->getRepository(Activity::class)->find($activityId);
        $this->assertNull($deletedActivity);
    }
}
