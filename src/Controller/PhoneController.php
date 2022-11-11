<?php

namespace App\Controller;

use App\Entity\Phone;
use App\Repository\PhoneRepository;
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

class PhoneController extends AbstractController
{   
    /**
     * Visualiser tous les téléphones
     * 
     * @OA\Response(
     *     response=201,
     *     description="Retourne la liste des téléphones",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Phone::class, groups={"getPhones"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="La page que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Le nombre d'éléments que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Client")
     *
     * @param PhoneRepository $phoneRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/phones', name: 'phones', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir les articles')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour voir les articles')]
    public function getAllPhones(PhoneRepository $phoneRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllPhones-" . $page . "-" . $limit;
        
        $jsonPhoneList = $cache->get($idCache, function (ItemInterface $item) use ($phoneRepository, $page, $limit, $serializer) {
            $item->tag("phonesCache");
            $phoneList = $phoneRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getPhones"]);
            return $serializer->serialize($phoneList, 'json', $context);
        });

        return new JsonResponse($jsonPhoneList, Response::HTTP_OK, [], true);
    }

    /**
     * Visualiser un téléphone en fonction de son id. 
     *
     *@OA\Response(
     *     response=200,
     *     description="Retoune un téléphone",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Phone::class, groups={"getPhones"}))
     *     )
     * )
     * @OA\Tag(name="Client")
     * 
     * @param Phone $phone
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/phones/{id}', name: 'detailPhone', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir un article')]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour voir un article')]
    public function getDetailPhone(Phone $phone, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getPhones"]);
        $context->setVersion($version);
        $jsonPhone = $serializer->serialize($phone, 'json', $context);
        return new JsonResponse($jsonPhone, Response::HTTP_OK, [], true);
    }
      
    /**
     * Supprimer un téléphone par rapport à son id. 
     * 
     *@OA\Response(
     *     response=200,
     *     description="supprimer un téléphone",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Phone::class, groups={"getPhones"}))
     *     )
     * )
     * @OA\Tag(name="Administration")
     *
     * @param Phone $phone
     * @param EntityManagerInterface $em
     * @return JsonResponse 
     */
    #[Route('/api/phones/{id}', name: 'deletePhone', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un téléphone')]
    public function deletePhone(Phone $phone, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse {
        $em->remove($phone);
        $em->flush();
        // On vide le cache.
        $cache->invalidateTags(["phonesCache"]);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Enregistrer un nouveau téléphone. 
     * Exemple de données : 
     * {
     *     "name": "Nom du modéle",
     *     "description": "Rapide description",
     *      "price": "en euro"
     * }
     * 
     *@OA\Response(
     *     response=200,
     *     description="Enregistrer un téléphone",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Phone::class, groups={"getPhones"}))
     *     )
     * )
     * @OA\Tag(name="Administration")
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     */
    #[Route('/api/phones', name:"createPhone", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un téléphone')]
    public function createPhone(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse {

        $phone = $serializer->deserialize($request->getContent(), Phone::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($phone);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        $content = $request->toArray();
        $phone->setAuthor($this->getUser())
            ->setCreatedAt(new \DateTimeImmutable);
        $em->persist($phone);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["phonesCache"]);

        $context = SerializationContext::create()->setGroups(["getPhones"]);
        $jsonPhone = $serializer->serialize($phone, 'json', $context);
		
        $location = $urlGenerator->generate('detailPhone', ['id' => $phone->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

		return new JsonResponse($jsonPhone, Response::HTTP_CREATED, ["Location" => $location], true);	
    }
    
    
    /**
     * Mise à jour un téléphone en fonction de son id. 
     * 
     * Exemple de données : 
     * {
     *     "name": "Nom du modéle",
     *     "description": "Rapide description",
     *     "price": "en euro"
     * }
     * 
     *@OA\Response(
     *     response=200,
     *     description="Mise à jour un téléphone",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Phone::class, groups={"getPhones"}))
     *     )
     * )
     * @OA\Tag(name="Administration")
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Phone $currentPhone
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/api/phones/{id}', name:"updatePhone", methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un téléphone')]
    public function updatePhone(Request $request, SerializerInterface $serializer,
                        Phone $currentPhone, EntityManagerInterface $em, 
                        ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse {
       
        $newPhone = $serializer->deserialize($request->getContent(), Phone::class, 'json');

        $currentPhone->setName($newPhone->getName());
        $currentPhone->setDescription($newPhone->getDescription());

        // On vérifie les erreurs
        $errors = $validator->validate($currentPhone);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();

        $em->persist($currentPhone);
        $em->flush();
        
        // On vide le cache. 
        $cache->invalidateTags(["phonesCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}