<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PhoneRepository;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Groups;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "detailPhone",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getPhones")
 * )
 *
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "deletePhone",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getPhones", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 * * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "updatePhone",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getPhones", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 */
#[ORM\Entity(repositoryClass: PhoneRepository::class)]
class Phone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["getPhones"])]
    #[Since("1.0")]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["getPhones"])]
    #[Assert\NotBlank(message: "Le nom du téléphone est obligatoire")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le nom doit faire au moins {{ limit }} caractère", maxMessage: "Le nom ne peut pas faire plus de {{ limit }} caractères")]
    #[Since("1.0")]
    private $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["getPhones"])]
    #[Since("1.0")]
    private $description;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(["getPhones"])]
    #[Since("1.0")]
    private $price;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'phones')]
    #[Groups(["getPhones"])]
    #[Since("1.0")]
    private $author;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(["getPhones"])]
    #[Since("1.0")]
    private $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}