<?php

namespace Shm\ShmTypes\CompositeTypes\BalanceTypes;

use Shm\ShmDB\mDB;
use Shm\ShmUtils\Inflect;

class BalanceGateway
{


    public ?float $minAmount = null;
    public ?float $maxAmount = null;

    public ?string $title = "";
    public ?string $description = "";

    public ?string $icon = null;

    public ?string $key = null;

    public function __construct($key)
    {
        $this->key = $key;
    }


    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setMinAmount(float $amount): static
    {
        $this->minAmount = $amount;
        return $this;
    }

    public function setMaxAmount(float $amount): static
    {
        $this->maxAmount = $amount;
        return $this;
    }

    /** @var callable(string, float):string */
    private  $paymentLinkGenerator = null;

    /**
     * Зарегистрировать генератор ссылки для валюты.
     * @param callable(string $user_id, float $amount): string $fn
     */
    public function setPaymentLinkGenerator(callable $fn): static
    {

        $this->paymentLinkGenerator = $fn;
        return $this;
    }

    public function generatePaymentLink(string $user_id, $amount): string
    {

        $fn = $this->paymentLinkGenerator ?? null;
        if (!$fn) {
            throw new \RuntimeException("Payment link generator not set");
        }

        return $fn(mDB::id($user_id), (float) $amount);
    }
}
