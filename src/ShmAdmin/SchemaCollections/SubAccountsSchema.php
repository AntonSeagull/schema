<?php


namespace Shm\ShmAdmin\SchemaCollections;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\MaterialIcons;

class SubAccountsSchema
{


    public static $collection = 'subAccounts';



    public static function updateSchema(StructureType $structure): StructureType
    {

        $subAccount = Auth::$subAccount;

        $collection = $structure->collection;


        $collectionItem = $subAccount[$collection] ?? null;



        $fullAccess = $collectionItem['fullAccess'] ?? false;

        $canView = $collectionItem['canView'] ?? false;

        if (!$canView) {


            $structure->canCreate(false)->canUpdate(false)->canDelete(false);
        } else {

            if (!$fullAccess) {


                $access = (array) ($collectionItem['access'] ?? []);



                if ($structure->canCreate && in_array('canCreate', $access)) {
                    $structure->canCreate(true);
                }

                if ($structure->canUpdate && in_array('canUpdate', $access)) {
                    $structure->canUpdate(true);
                }
                if ($structure->canDelete && in_array('canDelete', $access)) {
                    $structure->canDelete(true);
                }
            }
        }


        $hideFields =  (array) ($collectionItem['hideFields'] ?? []);


        $structure->hideFields($hideFields);



        return $structure;
    }


    public static function removeLockItemInSchema(StructureType $structure): StructureType
    {



        $subAccount = Auth::$subAccount;





        foreach ($structure->items as $key =>  $item) {
            if ($item->type == "adminGroup" || $item->type == 'admin') {
                $structure->items[$key] = self::removeLockItemInSchema($item);
                continue;
            }

            if ($item->type == 'structure' && $item instanceof StructureType) {


                $collection = $item->collection;


                $collectionItem = $subAccount[$collection] ?? null;





                $canView = $collectionItem['canView'] ?? false;

                if (!$canView) {
                    unset($structure->items[$key]);
                    continue;
                }
            }
        }


        return $structure;
    }


    private static function subAccountsSchema(StructureType $structure): array
    {

        $result = [];


        foreach ($structure->items as $item) {
            if ($item->type == "adminGroup" || $item->type == 'admin') {
                $result = [...$result, ...self::subAccountsSchema($item)];
            }

            if ($item->type == 'structure' && $item instanceof StructureType) {

                $enumItems = [];

                if ($item->canCreate) {
                    $enumItems['canCreate'] = "Создание";
                }
                if ($item->canUpdate) {
                    $enumItems['canUpdate'] = "Редактирование";
                }
                if ($item->canDelete) {
                    $enumItems['canDelete'] = "Удаление";
                }




                $buttonActionsEnum = [];
                if ($item->buttonActions && $item->buttonActions instanceof StructureType) {
                    foreach ($item->buttonActions->items as $buttonAction) {
                        if ($buttonAction->hide) continue;
                        $buttonActionsEnum[$buttonAction->key] = $buttonAction->title;
                    }
                }


                $hideFieldsEnum = [];


                foreach ($item->items as $subItem) {
                    if ($subItem->inAdmin) {
                        $hideFieldsEnum[$subItem->key] = $subItem->title;
                    }
                }



                $result = [
                    ...$result,

                    //                    Shm::visualGroup([

                    $item->collection => Shm::structure([


                        'canView' => Shm::bool()->title("Отображать раздел")->inAdmin()->editable()->default(true)->setCol(12),

                        'fullAccess' => Shm::bool()->title("Полный доступ")->inAdmin()->editable()->default(true)->cond(Shm::cond()->equals($item->collection . '.canView', true))->setCol(12),

                        'access' => count($enumItems) > 0 ?  Shm::arrayOf(Shm::enum($enumItems))->title("Доступ")->inAdmin()->editable()->cond(Shm::cond()->notEquals($item->collection . '.fullAccess', true)->equals($item->collection . '.canView', true)) : null,



                        'buttonActions' => count($buttonActionsEnum) > 0 ? Shm::arrayOf(Shm::enum($buttonActionsEnum))->title("Действия")->inAdmin()->editable()->cond(Shm::cond()->notEquals($item->collection . '.fullAccess', true)->equals($item->collection . '.canView', true)) : null,


                        'hideFields' => count($hideFieldsEnum) > 0 ? Shm::arrayOf(Shm::enum($hideFieldsEnum))->title("Скрыть поля")->inAdmin()->editable()->cond(Shm::cond()->notEquals($item->collection . '.fullAccess', true)->equals($item->collection . '.canView', true)) : null,

                    ])->title($item->title)->inAdmin()->editable()->col(8)


                    //       ])->title($item->title)->icon($item->assets['icon'] ?? null),
                ];
            }
        }


        return $result;
    }


    public static function baseStructure(): StructureType
    {
        $schema = Shm::structure([

            '_id' => Shm::ID(),
            'created_at' => Shm::timestamp(),
            'updated_at' => Shm::timestamp(),

            'photo' => Shm::fileImage()->title('Фото')->inAdmin()->editable()->inTable(),
            'active' => Shm::bool()->title('Разрешить доступ')->default(true)->inAdmin(!Auth::subAccountAuth())->editable()->inTable(),
            'name' => Shm::string()->title('Имя')->setCol(12)->inAdmin()->editable()->inTable(),
            'surname' => Shm::string()->title('Фамилия')->setCol(12)->inAdmin()->editable()->inTable(),
            'login' => Shm::login()->title('Логин')->setCol(12)->inAdmin()->editable()->inTable()->required(),
            'password' => Shm::password()->title('Пароль')->setCol(12)->inAdmin()->editable(),
            'last_used' => Shm::timestamp()->title('Последняя активность')->inAdmin(!Auth::subAccountAuth())->inTable(),


        ]);

        $schema->title("Доступы");
        $schema->icon('account-tag-outline');

        $schema->canCreate()->canDelete()->canUpdate();

        $schema->collection(self::$collection);
        $schema->key(self::$collection);


        $schema->insertValues([

            'owner' => Auth::getAuthOwner(),
            'collection' => Auth::getAuthCollection(),
        ]);




        return   $schema;
    }


    public static function structure(StructureType $adminSchema): StructureType
    {
        $schema = self::baseStructure();


        $schema->update([
            'access' =>  Shm::visualGroup(
                self::subAccountsSchema($adminSchema)
            )->title("Доступы"),
        ]);


        return   $schema;
    }
}
