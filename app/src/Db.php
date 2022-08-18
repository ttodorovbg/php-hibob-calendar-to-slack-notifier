<?php

declare(strict_types=1);

namespace App;

use SQLite3;

/**
 * Undocumented class
 */
class Db
{
    protected static SQLite3 $db;

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function init(): void
    {
        self::$db = new SQLite3(
            APP_DIR . '/sqlite/db.sqlite', 
            SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE
        );

        // Create users table.
        self::$db->query(
            'CREATE TABLE IF NOT EXISTS `users` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                `name` VARCHAR,
                `anniversary` VARCHAR,
                `birthday` VARCHAR,
                `deleted` DATE DEFAULT NULL,
                `created` DATE DEFAULT CURRENT_DATE
            )'
        );

        self::$db->query(
            'CREATE TABLE IF NOT EXISTS `bdays_hdays_notified` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                `name` VARCHAR,
                `type` VARCHAR,
                `created` DATE DEFAULT CURRENT_DATE
            )'
        );

        // delete old notifications
        self::$db->query(
            'DELETE FROM `bdays_hdays_notified` 
                WHERE `created` != CURRENT_DATE'
        );        
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public static function getActiveUsers(): array
    {
        $stmt = self::$db->prepare('SELECT * FROM `users` where `deleted` is null');

        $result = $stmt->execute();
        
        $users = [];
        
        if ($result->numColumns()) { 
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
                $users[] = $row;        
            }
        }

        return $users;
    }

    /**
     * Undocumented function
     *
     * @param array $calendar_results 
     * 
     * @return array
     */
    public static function addNewUsers(array $calendar_results): array
    {
        $added_user_ids = [];

        $stmt = self::$db->prepare(
            'INSERT INTO `users` (`name`, `anniversary`, `birthday`) ' . 
                'VALUES(:name, :anniversary, :birthday)'
        );
        
        foreach ($calendar_results as $name => $_arr) {
            
            foreach ($_arr as $anniversary => $birthday) {
        
                if (!$birthday) {
                    continue;
                }

                if ($birthday === true) {
                    $birthday = null;
                }

                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':anniversary', $anniversary, SQLITE3_TEXT);
                $stmt->bindValue(':birthday', $birthday, SQLITE3_TEXT);
            
                $stmt->execute();
                
                $added_user_ids[] = self::$db->lastInsertRowID();                
            }    
        }

        return $added_user_ids; 
    }

    /**
     * Undocumented function
     *
     * @param array $deleted_users 
     * 
     * @return void
     */
    public static function deleteUsers(array $deleted_users): void
    {
        // delete missing users
        $stmt = self::$db->prepare(
            'UPDATE `users` SET `deleted`=CURRENT_DATE WHERE `id`=:id'
        );

        foreach ($deleted_users as $user_id) {
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);            
            $stmt->execute();
        }
    }

    /**
     * Undocumented function
     *
     * @param array $user_ids 
     * 
     * @return array
     */
    public static function getDataForSlackNotification(array $user_ids): array
    {
        // add/remove
        $res['changes'] = [];

        if (count($user_ids)) {
            $in = '"' . implode('", "', $user_ids) . '"';

            $stmt = self::$db->prepare(
                'SELECT * FROM `users` WHERE `id` IN(' . $in . ') ORDER BY `deleted`'
            );
    
            $result = $stmt->execute();
                        
            if ($result->numColumns()) { 
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $res['changes'][] = $row;        
                }
            }
        }        

        // birthday
        $res['birthday'] = [];

        $stmt = self::$db->prepare(
            'SELECT * FROM `users` WHERE `birthday` = :birthday'
        );

        $stmt->bindValue(':birthday', date("md"), SQLITE3_TEXT);

        $result = $stmt->execute();
        
        if ($result->numColumns()) { 
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                // check if notified
                if (self::notified('birthday', $row['name'])) {

                    continue;
                }

                $res['birthday'][] = $row;

                self::addNotified('birthday', $row['name']);
            }
        }

        return $res;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function close(): void
    {
        self::$db->close();
    }

    /**
     * Undocumented function
     *
     * @param string $type 
     * @param string $name 
     * 
     * @return boolean
     */
    public static function notified(string $type, string $name): bool
    {
        $stmt = self::$db->prepare(
            'SELECT * FROM `bdays_hdays_notified` ' . 
                'WHERE `type` = :type ' .
                'AND `name` = :name'
        );

        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);

        $result = $stmt->execute();
        
        if ($result->numColumns()) { 
            if ($result->fetchArray(SQLITE3_ASSOC)) {

                return true;
            }
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @param string $type 
     * @param string $name 
     * 
     * @return void
     */
    public static function addNotified(string $type, string $name): void
    {
        $stmt = self::$db->prepare(
            'INSERT INTO `bdays_hdays_notified` (`type`, `name`) ' . 
                'VALUES (:type, :name)'
        );

        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);

        $stmt->execute();
    }
}