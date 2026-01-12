<?php

namespace Shm\ShmTypes\CompositeTypes\BalanceTypes;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class BalanceUtils
{

    public  const ALLOWED_CURRENCIES = ['USD', 'EUR', 'RUB', 'KZT'];

    public const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'RUB' => '₽',
        'KZT' => '₸',
    ];

    public static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (!self::isAllowedCurrency($currency)) {
            throw new \InvalidArgumentException(
                'Currency must be one of: ' . implode(', ', self::ALLOWED_CURRENCIES)
            );
        }
        return $currency;
    }

    public static function isAllowedCurrency(string $currency): bool
    {
        return in_array($currency, self::ALLOWED_CURRENCIES);
    }

    public static function balancePaymentsStructure(): StructureType
    {
        return Shm::structure([
            '_id' => Shm::ID(),
            "amount" => Shm::float(),
            "currency" => Shm::string(),
            "description" =>  Shm::string(),
            "created_at" => Shm::unixdatetime(),
            "beforeBalance" => Shm::float(),
            "afterBalance" => Shm::float(),
        ])->staticBaseTypeName("BalancePayment");
    }
}
