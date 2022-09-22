<?php

declare(strict_types=1);

namespace App;

use App\Cal;
use App\Db;

/**
 * Undocumented class
 */
class Main
{
    /**
     * Undocumented function
     *
     * @return void
     */
    public static function run(): void
    {
        Db::init();
        Cal::init();

        $calendar_results = Cal::fetch();
        $db_active_users = Db::getActiveUsers();

        $deleted_users = [];

        foreach ($db_active_users as $user) {
            if (empty($calendar_results[$user['name']][$user['anniversary']])) {
                // missing user from calendar
                $deleted_users[] = $user['id'];
            } else {
                $calendar_results[$user['name']][$user['anniversary']] = false;
            }
        }

        $added_users = Db::addNewUsers($calendar_results);

        Db::deleteUsers($deleted_users);

        $data = DB::getDataForSlackNotification(
            array_merge($added_users, $deleted_users)
        );

        $data['holiday'] = Cal::fetchHolidays();
        $db_active_users = Db::getActiveUsers();
        $data['total'][] = ['total' => count($db_active_users)];

        Db::close();

        Slack::notify($data);
        Cal::cleanAndArchive($data);
    }
}
