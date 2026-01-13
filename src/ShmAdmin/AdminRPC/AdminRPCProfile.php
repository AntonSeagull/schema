<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\CompositeTypes\BalanceTypes\BalanceUtils;

class AdminRPCProfile
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                "type" => Shm::structure([

                    'structure' => BaseStructureType::get(),
                    'data' => Shm::mixed(),
                    'changePassword' => Shm::boolean(),
                    'subAccount' => Shm::boolean(),

                    'balances' => Shm::arrayOf(Shm::structure([
                        'field' => BaseStructureType::get(),
                        'path' => Shm::string(),
                    ])),
                ]),

                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);


                    if (Auth::subAccountAuth()) {

                        $findStructure = SubAccountsSchema::baseStructure();
                    } else {

                        $findStructure = null;


                        foreach (AdminPanel::$authStructures as $user) {

                            if ($user->collection == Auth::getAuthCollection()) {
                                $findStructure = $user;
                                break;
                            }
                        }
                    }
                    if (!$findStructure) {
                        ShmRPC::error("Ошибка доступа");
                    }


                    $passwordField =  $findStructure->findItemByType(Shm::password());

                    if ($passwordField)
                        $findStructure->items[$passwordField->key]->inAdmin(false);


                    $emailField = $findStructure->findItemByType(Shm::email());
                    $loginField = $findStructure->findItemByType(Shm::login());
                    $phoneField = $findStructure->findItemByType(Shm::phone());


                    if ($emailField) {
                        $findStructure->items[$emailField->key]->editable(false)->setCol(24);
                    }
                    if ($loginField) {
                        $findStructure->items[$loginField->key]->editable(false)->setCol(24);
                    }
                    if ($phoneField) {
                        $findStructure->items[$phoneField->key]->editable(false)->setCol(24);
                    }

                    $balances = [];
                    foreach (BalanceUtils::ALLOWED_CURRENCIES as $currency) {

                        $field = $findStructure->findBalanceFieldByCurrency($currency);

                        if ($field) {

                            $balances[] = [
                                'field' => $field->json(),
                                'path' => $field->getPathString(),
                            ];
                        }
                    }



                    return [
                        'structure' => $findStructure->json(),
                        'data' => $findStructure->removeOtherItems($findStructure->normalize($findStructure->findOne([
                            '_id' => Auth::subAccountAuth() ? Auth::getSubAccountID() :  Auth::getAuthID()
                        ]))),
                        'subAccount' => Auth::subAccountAuth(),
                        'balances' =>  Auth::subAccountAuth() ? [] : $balances,
                        'changePassword' => $passwordField ? true : false,
                    ];
                }
            ];
        });
    }
}
