<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservation_item')]
#[ORM\UniqueConstraint(name: 'unique_reservation_warehouse_product', columns: ['reservation_id', 'warehouse_id', 'product_id'])]
#[ORM\Index(name: 'idx_reservation_item_reservation', columns: ['reservation_id'])]
#[ORM\Index(name: 'idx_reservation_item_warehouse', columns: ['warehouse_id'])]
#[ORM\Index(name: 'idx_reservation_item_product', columns: ['product_id'])]
class ReservationItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservationItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    public function __construct(Reservation $reservation, Warehouse $warehouse, Product $product, int $quantity)
    {
        $this->reservation = $reservation;
        $this->warehouse = $warehouse;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        if ($this->reservation === $reservation) {
            return $this;
        }

        $this->reservation = $reservation;

        return $this;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
