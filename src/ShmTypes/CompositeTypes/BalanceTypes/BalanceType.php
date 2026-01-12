<?php

namespace Shm\ShmTypes\CompositeTypes\BalanceTypes;

use Shm\ShmDB\mDB;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\CompositeTypes\BalanceTypes\BalanceUtils;
use Shm\ShmTypes\FloatType;
use Shm\ShmTypes\StringType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Inflect;

class BalanceType extends BaseType
{
    public string $type = 'balance';

    public string  $currency;

    public string  $currencySymbol;


    public function __construct(string $currency)
    {

        $this->currency =  BalanceUtils::normalizeCurrency($currency);
        $this->currencySymbol =  BalanceUtils::CURRENCY_SYMBOLS[$this->currency] ?? $this->currency;
        $this->editable = false;
        $this->title($this->currencySymbol);
    }

    public function editable(bool $editable = true): static
    {
        $this->editable = false;
        return $this;
    }

    public  $gateways = [];

    public function addGateway(BalanceGateway $gateway): static
    {
        $this->gateways[$gateway->key] = $gateway;
        return $this;
    }
    public function getGateway(string $key): ?BalanceGateway
    {
        return $this->gateways[$key] ?? null;
    }

    public  function lastBalanceOperations(string $_id,  int $limit = 1000): array
    {

        $collection = $this->getParentCollection();
        $key = Inflect::singularize($collection);

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

        $cursor = mDB::collection($collection . '_payments')->find($filter, $options);


        return iterator_to_array($cursor, false);
    }


    public function addCurrencyBalance($user_id, $amount, $description)
    {
        $collection = $this->getParentCollection();

        $user = mDB::collection($collection)->findOne([
            "_id" => mDB::id($user_id),
        ]);


        if (!$user) {
            throw new \Exception("User not found for " . $user_id . " in collection " . $collection);
        }
        if (!$amount) return false;



        $beforeBalance = $user['_balance'][$this->currency] ?? 0;

        $afterBalance = $beforeBalance + (float) $amount;

        $key = Inflect::singularize($collection);

        mDB::collection($collection . "_payments")->insertOne([

            //@deprecated
            "manager" => $user->_id,
            $key => $user->_id,
            "userCollection" => $collection,
            "amount" => (int) $amount,
            "currency" => $this->currency,
            "description" =>  $description ?? "Операция по балансу",
            "created_at" => time(),
            "beforeBalance" => $beforeBalance,
            "afterBalance" => $afterBalance,

        ]);
        $path = $this->getPathString();

        mDB::collection($collection)->updateOne([
            "_id" => mDB::id($user_id),
        ], [
            '$set' => [

                $path  => $afterBalance
            ]
        ]);
    }
}
