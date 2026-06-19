<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReservationStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'reservation')]
    #[ORM\JoinColumn(nullable: false)]
    private CustomerOrder $customerOrder;

    #[ORM\Column(type: 'string', length: 32, enumType: ReservationStatus::class)]
    private ReservationStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ReservationItem> */
    #[ORM\OneToMany(
        mappedBy: 'reservation',
        targetEntity: ReservationItem::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $reservationItems;

    public function __construct(CustomerOrder $customerOrder)
    {
        $this->customerOrder = $customerOrder;
        $this->status = ReservationStatus::Active;
        $this->createdAt = new \DateTimeImmutable();
        $this->reservationItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerOrder(): CustomerOrder
    {
        return $this->customerOrder;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ReservationItem> */
    public function getReservationItems(): Collection
    {
        return $this->reservationItems;
    }

    public function addReservationItem(ReservationItem $reservationItem): static
    {
        if (!$this->reservationItems->contains($reservationItem)) {
            $this->reservationItems->add($reservationItem);
            $reservationItem->setReservation($this);
        }

        return $this;
    }

    public function removeReservationItem(ReservationItem $reservationItem): static
    {
        $this->reservationItems->removeElement($reservationItem);

        return $this;
    }
}
