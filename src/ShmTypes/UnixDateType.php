<?php

namespace Shm\ShmTypes;


use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class UnixDateType extends BaseType
{
    public string $type = 'unixdate';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  !$value  && $this->defaultIsSet) {
            return $this->getDefault();
        }
        return (int) $value;
    }

    /**
     * Validate that the value is a valid Unix timestamp (int).
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a Unix timestamp (integer).");
        }
    }



    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter = Shm::structure([
            'gte' => Shm::unixdate()->title('Больше')->setCol(12),
            'lte' => Shm::unixdate()->title('Меньше')->setCol(12),
            'eq' => Shm::unixdate()->title('Равно'),
            'fromNow' => Shm::enum([
                "lastDay" => "Текущий день",
                "last3Days" => "Последние 3 дня",
                "last6Days" => "Последние 6 дней",
                "last12Days" => "Последние 12 дней",
                "lastWeek" => "Текущая неделя",
                "last2Weeks" => "Последние 2 недели",
                "last4Weeks" => "Последние 4 недель",
                "last8Weeks" => "Последние 8 недель",
                "lastMonth" => "Текущий месяц",
                "last2Months" => "Последние 2 месяца",
                "last3Months" => "Последние 3 месяца",
                "last6Months" => "Последние 6 месяцев",
            ])->title('От текущего момента'),

        ])->editable()->staticBaseTypeName("UnixDateFilterType");

        return $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title);
    }


    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {


        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;


        $match = [];

        if (isset($filter['gte'])) {
            $match['$gte'] = (int) $filter['gte'];
        }
        if (isset($filter['eq'])) {
            $match['$eq'] = (int) $filter['eq'];
        }
        if (isset($filter['lte'])) {
            $match['$lte'] = (int) $filter['lte'];
        }

        if (isset($filter['fromNow'])) {

            $fromNow = $filter['fromNow'];

            switch ($fromNow) {
                case "lastDay":
                    // Начало текущего дня (00:00:00)
                    $match['$gte'] = mktime(0, 0, 0);
                    break;
                case "last3Days":
                    // Начало дня, который был 3 дня назад
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - 3);
                    break;
                case "last6Days":
                    // Начало дня, который был 6 дней назад
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - 6);
                    break;
                case "last12Days":
                    // Начало дня, который был 12 дней назад
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - 12);
                    break;
                case "lastWeek":
                    // Начало текущей недели (понедельник 00:00:00)
                    $dayOfWeek = date('w'); // 0 = воскресенье, 1 = понедельник
                    $daysToMonday = $dayOfWeek == 0 ? 6 : $dayOfWeek - 1;
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - $daysToMonday);
                    break;
                case "last2Weeks":
                    // Начало недели, которая была 2 недели назад
                    $dayOfWeek = date('w');
                    $daysToMonday = $dayOfWeek == 0 ? 6 : $dayOfWeek - 1;
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - $daysToMonday - 14);
                    break;
                case "last4Weeks":
                    // Начало недели, которая была 4 недели назад
                    $dayOfWeek = date('w');
                    $daysToMonday = $dayOfWeek == 0 ? 6 : $dayOfWeek - 1;
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - $daysToMonday - 28);
                    break;
                case "last8Weeks":
                    // Начало недели, которая была 8 недель назад
                    $dayOfWeek = date('w');
                    $daysToMonday = $dayOfWeek == 0 ? 6 : $dayOfWeek - 1;
                    $match['$gte'] = mktime(0, 0, 0, date('m'), date('d') - $daysToMonday - 56);
                    break;
                case "lastMonth":
                    // Начало текущего месяца (1 число, 00:00:00)
                    $match['$gte'] = mktime(0, 0, 0, date('m'), 1);
                    break;
                case "last2Months":
                    // Начало месяца, который был 2 месяца назад
                    $match['$gte'] = mktime(0, 0, 0, date('m') - 2, 1);
                    break;
                case "last3Months":
                    // Начало месяца, который был 3 месяца назад
                    $match['$gte'] = mktime(0, 0, 0, date('m') - 3, 1);
                    break;
                case "last6Months":
                    // Начало месяца, который был 6 месяцев назад
                    $match['$gte'] = mktime(0, 0, 0, date('m') - 6, 1);
                    break;
            }
        }



        if (empty($match)) {
            return null;
        }
        return [
            [
                '$match' => [
                    $path => $match
                ]
            ]
        ];



        return null;
    }




    public function tsType(): TSType
    {
        $TSType = new TSType("number");


        return $TSType;
    }

    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            return date('d.m.Y', $value);
        } else {
            return null;
        }
    }
}
