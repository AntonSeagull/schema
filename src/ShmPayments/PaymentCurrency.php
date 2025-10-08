<?php

namespace Shm\ShmPayments;

use Shm\ShmDB\mDB;
use Shm\ShmUtils\Inflect;

class PaymentCurrency
{

    public  const ALLOWED_CURRENCIES = ['USD', 'EUR', 'RUB', 'KZT'];

    public string  $currency;


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




    public ?string $collection;

    public function getGateway(string $key): ?PaymentGateway
    {
        return $this->paymentGateways[$key] ?? null;
    }

    public function balance($user_id)
    {

        $user = mDB::collection($this->collection)->findOne([
            "_id" => mDB::id($user_id),
        ]);

        if (!$user) return 0;
        return $user['_balance'][$this->currency] ?? 0;
    }


    private  $paymentGateways = [];

    public function addGateway(PaymentGateway $gateway): static
    {
        $this->paymentGateways[$gateway->key] = $gateway;
        return $this;
    }

    /**
     * Вернуть последние N (по умолчанию 1000) операций по балансу для пользователя и валюты.
     *
     * @param string $_id  ID пользователя
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public  function lastBalanceOperations(string $_id,  int $limit = 1000): array
    {


        $key = Inflect::singularize($this->collection);

        $filter = [
            '$or' => [
                [$key  => mDB::id($_id)],
                ['manager' => mDB::id($_id)],
            ],   // в платежи ты писал $user->_id
            'currency' => $this->currency,
            'deleted_at' => ['$exists' => false],
        ];

        // сортировка и лимит (последние по времени)
        $options = [
            'sort'   => ['created_at' => -1],
            'limit'  => $limit,
        ];

        $cursor = mDB::collection($this->collection . '_payments')->find($filter, $options);

        // если нужен обычный массив
        return iterator_to_array($cursor, false);
    }


    public function addCurrencyBalance($_id, $amount, $description)
    {


        $user = mDB::collection($this->collection)->findOne([
            "_id" => mDB::id($_id),
        ]);

        if (!$user) return false;
        if (!$amount) return false;



        $beforeBalance = $user['_balance'][$this->currency] ?? 0;

        $afterBalance = $beforeBalance + (float) $amount;

        $key = Inflect::singularize($this->collection);

        mDB::collection($this->collection . "_payments")->insertOne([

            //@deprecated
            "manager" => $user->_id,
            $key => $user->_id,
            "userCollection" => $this->collection,
            "amount" => (int) $amount,
            "currency" => $this->currency,
            "description" =>  $description ?? "Операция по балансу",
            "created_at" => time(),
            "beforeBalance" => $beforeBalance,
            "afterBalance" => $afterBalance,

        ]);

        mDB::collection($this->collection)->updateOne([
            "_id" => mDB::id($_id),
        ], [
            '$set' => [
                '_balance.' . $this->currency => $afterBalance
            ]
        ]);
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,

            'gateways' => array_map(function (PaymentGateway $g) {
                return $g->toArray();
            }, array_values($this->paymentGateways)),
        ];
    }
}
