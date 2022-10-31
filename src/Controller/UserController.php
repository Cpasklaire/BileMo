<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{   
    private $userPasswordHasher;
 
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    /**
     * Visualiser tous les utilisateurs
     * 
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des utilisateur",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="La page que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Le nombre d'éléments que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Client")
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir les users')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour voir les users')]
    public function getAllUsers(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllUsers-" . $page . "-" . $limit;
        
        $jsonUserList = $cache->get($idCache, function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer) {
            $item->tag("usersCache");
            $userList = $userRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getUsers"]);
            return $serializer->serialize($userList, 'json', $context);
        });

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    /**
     * Visualiser un utilisateur en fonction de son id. 
     *
     *@OA\Response(
     *     response=200,
     *     description="Retoune un utilisateur",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     * @OA\Tag(name="Client")
     * 
     * @param User $user
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name: 'detailUser', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir un user')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour voir un user')]
    public function getDetailUser(User $user, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getUsers"]);
        $context->setVersion($version);
        $jsonUser = $serializer->serialize($user, 'json', $context);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
      
    /**
     * Supprimer un user par rapport à son id. 
     * 
     *@OA\Response(
     *     response=200,
     *     description="supprimer un user",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     * @OA\Tag(name="Client")
     *
     * @param User $user
     * @param EntityManagerInterface $em
     * @return JsonResponse 
     */
    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un user')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour supprimer un user')]
    public function deleteUser(User $user, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse {
        $em->remove($user);
        $em->flush();
        // On vide le cache.
        $cache->invalidateTags(["usersCache"]);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Enregistrer un nouveau user. 
     * Exemple de données : 
     * {
     *     "email": "example@mail.com",
     *     "password": "*****",
     *     "company": "Votre entreprise"
     * }
     * 
     *@OA\Response(
     *     response=200,
     *     description="Enregistrer un user",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     * @OA\Tag(name="Client")
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     * @param UserPasswordHasherInterface $userPasswordHasher
     */
    #[Route('/api/users', name:"createUser", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un user')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour créer un user')]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, TagAwareCacheInterface $cache, UserPasswordHasherInterface $userPasswordHasher): JsonResponse {
        
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        $content = $request->toArray();
        $user->setCreatedAt(new \DateTimeImmutable);
        if($user->getCompany() == "BileMo"){
            $user->setRoles(["ROLE_ADMIN"]);
        } else {
            $user->setRoles(["ROLE_USER"]);
        }
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $user->getPassword()));
        $em->persist($user);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["usersCache"]);

        $context = SerializationContext::create()->setGroups(["getUsers"]);
        $jsonUser = $serializer->serialize($user, 'json', $context);
		
        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

		return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);	
    }
    
    
    /**
     * Mise à jour un user en fonction de son id. 
     * 
     * Exemple de données : 
     * {
     *     "email": "example@mail.com",
     *     "password": "*****",
     *     "company": "Votre entreprise"
     * }
     * 
     *@OA\Response(
     *     response=200,
     *     description="Mise à jour un user",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     * @OA\Tag(name="Client")
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param User $currentUser
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name:"updateUser", methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un user')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour éditer un user')]
    public function updateUser(Request $request, SerializerInterface $serializer, User $currentUser, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse {
       
        $newUser = $serializer->deserialize($request->getContent(), User::class, 'json');

        $currentUser->setName($newUser->getName());
        $currentUser->setDescription($newUser->getDescription());

        // On vérifie les erreurs
        $errors = $validator->validate($currentUser);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();

        $em->persist($currentUser);
        $em->flush();
        
        // On vide le cache. 
        $cache->invalidateTags(["usersCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}