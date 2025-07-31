<?php

use Huncwot\UhoFramework\_uho_fx;

/**
 * Model class for Hello widget
 */

class serdelia_widget_hello
{

    var $params;
    var $orm;
    var $lang_add;
    var $parent;

    /**
     * Constructor
     * @param object $orm instance of _uho_orm class
     * @param array $params
     */

    public function __construct($orm, $params)
    {
        $this->orm = $orm;
        $this->params = $params;
    }

    /**
     * Loads model data to be rendered by View
     * @return array
     */

    public function getData()
    {

        $log = $this->orm->getJsonModel('cms_users_logs_logins', ['login' => $this->params['user']['login'], 'success' => 1], false, 'datetime DESC', '0,2');
        if (isset($log[1])) {
            $translate = [
                'pl' =>
                [
                    'long_time_ago' => 'ponad rok',
                    'months_ago' => 'miesięcy',
                    'days_ago' => 'dni',
                    'hours_ago' => 'godz.',
                    'minutes_ago' => 'min.',
                    'moment_ago' => 'Minęła zaledwie chwila'

                ],
                'en' =>
                [
                    'long_time_ago' => 'over a year',
                    'months_ago' => 'months',
                    'days_ago' => 'days',
                    'hours_ago' => 'hours',
                    'minutes_ago' => 'minutes',
                    'moment_ago' => 'just a moment'

                ]


            ];

            $translate = $translate[$this->params['lang']];
            if (isset($log[1])) $log = $log[1]; else $log=null;

            if (isset($log))
            {
                $date = new DateTime($log['datetime']);
                $interval = $date->diff(new DateTime());
                $diff = strtotime(date('Y-m-d H:i:s')) - strtotime($log['datetime']);
                $hours = intval($diff / 60 / 60);
                if ($interval->format('%y') > 0) $days = $translate['long_time_ago'];
                elseif ($interval->format('%m') > 1) $days = $interval->format('%m') . ' ' . $translate['months_ago'];
                elseif ($interval->format('%d') > 1) $days = [$interval->format('%d'), $translate['days_ago']];
                elseif ($hours > 1) $days = [intval($diff / 60 / 60), 'godziny'];
                elseif ($interval->format('%m') > 1) $days = intval($diff / 60) . ' ' . $translate['minutes_ago'];
                else $days = $translate['moment_ago'];
            }

            if (is_array($days)) {
                if ($this->params['lang'] == 'pl')
                    $t = [
                        'dni' => ['dzień', 'dni', 'dni', 'minął', 'minęły', 'minęło'],
                        'godziny' => ['godzina', 'godziny', 'godzin', 'minęła', 'minęły', 'minęło']
                    ];
                else
                    $t = [
                        'dni' => ['day', 'days', 'days', '', '', ''],
                        'godziny' => ['hour', 'hours', 'hours', '', '', '']
                    ];


                $type = $days[1];
                $declination = _uho_fx::utilsNumberDeclinationPL($days[0]);
                if ($t[$days[1]]) $days[1] = $t[$days[1]][$declination];
                if (isset($t[$type][$declination + 2])) $first = ucfirst($t[$type][$declination + 2]);
                else $first = '';
                $days = $first . ' ' . $days[0] . ' ' . $t[$type][$declination - 1];
            }
        }

        return ['result' => true, 'ago' => $days, 'name' => $this->params['user']['name']];
    }
}
