<?php

namespace App\ShmMetrics;

class ShmMetric
{

    public $type = 'metric';
    public $title = 'Метрика';

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function title(string $title): ShmMetric
    {
        $this->title = $title;
        return $this;
    }


    /*  'type' => $this->type,

            'title' => $this->title,

            'main' => [
                [
                    'view' => 'pie',
                    'title' => 'Cегодня',
                    'result' => $todayResult,
                ],
                [
                    'view' => 'pie',
                    'title' => 'За неделю',
                    'result' => $weekResult,
                ],
                [
                    'view' => 'pie',
                    'title' => 'За месяц',
                    'result' => $monthResult,
                ],
                [
                    'view' => 'pie',
                    'title' =>  'За все время',
                    'result' => $result,
                ],


            ],
*/
}
