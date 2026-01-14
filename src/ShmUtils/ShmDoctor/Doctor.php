<?php

namespace Shm\ShmUtils\ShmDoctor;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use Shm\ShmCmd\Cmd;
use Shm\ShmUtils\ShmDoctor\ClassGenerator\AdminDescriptionGenerator;

use Shm\ShmUtils\ShmDoctor\ClassGenerator\FieldClassGenerator;
use Shm\ShmUtils\ShmDoctor\ClassGenerator\ItemClassGenerator;

class Doctor
{
    /**
     * Initialize CLI menu for doctor commands
     */
    public static function cmdInit(): void
    {
        Cmd::command("doctor", function () {
            $menu = (new CliMenuBuilder)
                ->setTitle('Выберите действие')
                ->addItem('Обновить FieldClasses', function () {
                    FieldClassGenerator::generateAll();
                    exit;
                })
                ->addItem('Обновить ItemClasses', function () {
                    ItemClassGenerator::generateAll();
                    exit;
                })
                ->addItem('Обновить файл локализации и описания для админ. панели', function () {
                    AdminDescriptionGenerator::generateAll();
                    exit;
                })
                ->addItem('Обновить MongoDB индексы', function () {
                    IndexManager::ensureIndexes();
                    exit;
                })
                ->addItem('Создать символические ссылки для админ. панели', function () {
                    SymlinkManager::createSymlinks();
                    exit;
                })
                ->addItem('Создать файл config.php', function () {
                    ConfigGenerator::generate();
                    exit;
                })
                ->build();

            $menu->open();
        });
    }

    /**
     * Run all doctor tasks
     */
    public static function runAll(): void
    {
        IndexManager::ensureIndexes();
        FieldClassGenerator::generateAll();
        SymlinkManager::createSymlinks();
    }
}
