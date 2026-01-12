<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmRPC\ShmRPC;

class AdminRPCGeneratePaymentLink
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::string(),
                'args' => Shm::structure([
                    'currency' =>  Shm::nonNull(Shm::string()),
                    'gateway' => Shm::nonNull(Shm::string()),
                    'amount' => Shm::nonNull(Shm::float()),
                ]),
                'resolve' => function ($root, $args) {

                    /*                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $findStructure = AdminPanel::findCurrentAuthStructure();

                    if (!$findStructure) {
                        Response::validation("Ошибка оплаты. Попробуйте позже");
                    }

                    $amount = $args['amount'] ?? 0;
                    $currency = $args['currency'] ?? null;

                    if ($amount <= 0) {
                        Response::validation("Сумма должна быть больше нуля");
                    }

                    if (!in_array($currency, StructureType::ALLOWED_CURRENCIES)) {
                        Response::validation("Валюта не поддерживается");
                    }


                    return $findStructure->getCurrency($currency)?->getGateway($args['gateway'])?->generatePaymentLink(Auth::getAuthID(), $amount);*/
                }
            ];
        });
    }
}
