<?php

namespace Shm\ShmTypes\Utils\Payments;


class PaymentCurrency
{

    public  const ALLOWED_CURRENCIES = ['USD', 'EUR', 'RUB', 'KZT'];

    public string  $currency;

    public ?float $minAmount = null;
    public ?float $maxAmount = null;

    public function __construct(string $currency)
    {

        $this->currency = $this->normalizeCurrency($currency);
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (!in_array($currency, self::ALLOWED_CURRENCIES)) {
            throw new \InvalidArgumentException(
                'Currency must be one of: ' . implode(', ', self::ALLOWED_CURRENCIES)
            );
        }
        return $currency;
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

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'minAmount' => $this->minAmount,
            'maxAmount' => $this->maxAmount,
        ];
    }
}
