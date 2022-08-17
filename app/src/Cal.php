<?php

declare(strict_types=1);

namespace App;

use ICal\ICal;

/**
 * Undocumented class
 */
class Cal
{
    public static string $file_name;
    
    /**
     * Undocumented function
     *
     * @return void
     */
    public static function init()
    {
        self::$file_name = APP_DIR . '/ics/hibob-' . date('Y-m-d_h-i-s') . '.ics';
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
        
        file_put_contents(self::$file_name, $file_contents);

        try {
            $ical = new ICal(
                self::$file_name, [
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
    public static function archive(array $data): void
    {
        if (!count($data['changes'])) {
            // delete file
            unlink(self::$file_name);
            print 'UNLINK';
            return;
        }
        
        $zip = new \ZipArchive();

        if ($zip->open(APP_DIR . '/ics/bob.ics.zip', \ZipArchive::CREATE) === true) {
            // Add files to the zip file
            $zip->addFile(self::$file_name);
            $zip->setCompressionName(self::$file_name, \ZipArchive::CM_LZMA, 9);
         
            $zip->close();

            unlink(self::$file_name);
        }

        return;
    }
}