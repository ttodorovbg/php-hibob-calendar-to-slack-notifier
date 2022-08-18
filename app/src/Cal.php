<?php

declare(strict_types=1);

namespace App;

use ICal\ICal;

/**
 * Undocumented class
 */
class Cal
{
    /**
     * Anniversaries and birthdays filename
     *
     * @var string
     */
    public static string $file_name1;

    /**
     * Holidays filename
     *
     * @var string
     */
    public static string $file_name2;
    
    /**
     * Undocumented function
     *
     * @return void
     */
    public static function init()
    {
        self::$file_name1 
            = APP_DIR . '/ics/hibob-' . date('Y-m-d_h-i-s') . '.ics';

        self::$file_name2 
            = APP_DIR . '/ics/hibob-holidays-' . date('Y-m-d_h-i-s') . '.ics';
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public static function fetch(): array
    {
        $file_contents = file_get_contents(
            getenv('HIBOB_BIRTHDAYS_AND_WORK_ANNIVERSARIES_ICS_URL')
        );
        //$file_contents = file_get_contents(
        //    APP_DIR . '/ics/hibob-2022-08-12_06-07-10.ics'
        //);

        $fetched_results = [];
        
        file_put_contents(self::$file_name1, $file_contents);

        try {
            $ical = new ICal(
                self::$file_name1, [
                    'defaultSpan'                 => 2,     // Default value
                    'defaultTimeZone'             => 'UTC',
                    'defaultWeekStart'            => 'MO',  // Default value
                    'disableCharacterReplacement' => false, // Default value
                    'filterDaysAfter'             => null,  // Default value
                    'filterDaysBefore'            => null,  // Default value
                    'httpUserAgent'               => null,  // Default value
                    'skipRecurrence'              => false, // Default value
                ]
            );    
        } catch (\Exception $e) {
            die($e);
        }
        
        $b_name = '';
        $birthday = '';
        
        foreach ($ical->cal['VEVENT'] as $event) {

            $pos = strpos($event['SUMMARY'], ' has a birthday');
            
            if ($pos !== false) {
                $b_name = substr($event['SUMMARY'], 0, $pos);
                $birthday = substr($event['DTSTART'], 4);

                continue;
            }
            
            $pos = strpos($event['SUMMARY'], ' has a work anniversary');
            
            if ($pos === false) {
                continue;
            }

            $name = substr($event['SUMMARY'], 0, $pos);
            $anniversary = substr($event['DTSTART'], 4);

            $fetched_results[$name][$anniversary] 
                = ($name == $b_name) ? $birthday : true;
        }

        return $fetched_results;
    }

    /**
     * Undocumented function
     *
     * @param array $data 
     * 
     * @return void
     */
    public static function cleanAndArchive(array $data): void
    {
        unlink(self::$file_name2);

        if (!count($data['changes'])) {
            // delete file
            unlink(self::$file_name1);
            print 'UNLINK';
            return;
        }
        
        $zip = new \ZipArchive();

        if ($zip->open(APP_DIR . '/ics/bob.ics.zip', \ZipArchive::CREATE) === true) {
            // Add files to the zip file
            $zip->addFile(self::$file_name1);
            $zip->setCompressionName(self::$file_name1, \ZipArchive::CM_LZMA, 9);
         
            $zip->close();

            unlink(self::$file_name1);
        }

        return;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public static function fetchHolidays(): array
    {
        $file_contents = file_get_contents(
            getenv('HIBOB_HOLIDAYS_ICS_URL')
        );
        
        //self::$file_name2 =APP_DIR . '/ics/hibob-holidays.ics';
        

        $fetched_results = [];
        
        file_put_contents(self::$file_name2, $file_contents);

        try {
            $ical = new ICal(
                self::$file_name2, [
                    'defaultSpan'                 => 2,     // Default value
                    'defaultTimeZone'             => 'UTC',
                    'defaultWeekStart'            => 'MO',  // Default value
                    'disableCharacterReplacement' => false, // Default value
                    'filterDaysAfter'             => null,  // Default value
                    'filterDaysBefore'            => null,  // Default value
                    'httpUserAgent'               => null,  // Default value
                    'skipRecurrence'              => false, // Default value
                ]
            );    
        } catch (\Exception $e) {
            print_r($e);

            return [];
        }

        $today = new \DateTime(); // Today
        
        foreach ($ical->cal['VEVENT'] as $event) {

            $start_date = new \DateTime($event['DTSTART']);
            $end_date = new \DateTime($event['DTEND']);

            if ($today >= $start_date && $today < $end_date) {

                if (Db::notified('holiday', $event['DESCRIPTION'])) {

                    continue;
                }

                $fetched_results[] = [
                    'name' => $event['DESCRIPTION'],
                ];

                Db::addNotified('holiday', $event['DESCRIPTION']);
            }            
        }

        return $fetched_results;
    }
}