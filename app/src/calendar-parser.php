<?php

require_once '../vendor/autoload.php';

use ICal\ICal;

$file_contents = file_get_contents(getenv('ICS_URL'));

$file_name = '../ics/bob-' . date('Y-m-d_h-i-s') . '.ics';
file_put_contents($file_name, $file_contents);

try {
    $ical = new ICal(
        $file_name, [
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

$cal_res = [];

foreach ($ical->cal['VEVENT'] as $event) {
    $pos = strpos($event['SUMMARY'], ' has a work anniversary');
    
    if ($pos === false) {
        continue;
    }

    $name = substr($event['SUMMARY'], 0, $pos);
    $anniversary = substr($event['DTSTART'], 4);
    $cal_res[$name][$anniversary] = true;
}

$db = new SQLite3(
    '../sqlite/db.sqlite', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE
);

// Create a table.
$db->query(
    'CREATE TABLE IF NOT EXISTS `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    `name` VARCHAR,
    `anniversary` VARCHAR,
    `deleted` DATE DEFAULT NULL,
    `created` DATE DEFAULT CURRENT_DATE
  )'
);

// Get a count of the number of users
//$userCount = $db->querySingle('SELECT COUNT(DISTINCT "id") FROM "users"');
//echo("User count: $userCount\n");

$stmt = $db->prepare('SELECT * FROM `users` where `deleted` is null');

$result = $stmt->execute();

$missing_users = [];
$db_res = [];

if ($result->numColumns()) { 
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
        $db_res[] = $row;

        if (empty($cal_res[$row['name']][$row['anniversary']])) {
            // missing user
            $missing_users[] = $row['id'];
        } else {
            $cal_res[$row['name']][$row['anniversary']] = false;
        }
    }
}

//print_r($db_res);
//print_r($cal_res);

// insert new users
$stmt = $db->prepare(
    'insert into users (`name`, `anniversary`) values(:name, :anniversary)'
);

foreach ($cal_res as $name => $anniversary) {
    
    foreach ($anniversary as $_anniversary => $_insert) {

        if (!$_insert) {
            continue;
        }
        print "\n insert " . $name . ' ' . $_anniversary . "\n";
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':anniversary', $_anniversary, SQLITE3_TEXT);
    
        $result = $stmt->execute();
    }    
}

// delete missing users
$stmt = $db->prepare('update `users` set `deleted`=CURRENT_DATE where `id`=:id');

foreach ($missing_users as $missing_id) {
    print "\n Missing " . $missing_id . "\n";
    $stmt->bindValue(':id', $missing_id, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
}

// check deleted
$stmt = $db->prepare('SELECT * FROM `users` where `deleted` is not null');
$result = $stmt->execute();
$db_res = [];
if ($result->numColumns()) { 
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
        $db_res[] = $row;        
    }
}

print_r($db_res);

slackNotify($db_res);

// Close the connection
$db->close();

function slackNotify(array $data)
{
    $attachments = [];

    foreach ($data as $entry) {
        $attachments[] = [
            'fields' => [
                [
                    'title' => 'Name', 
                    'value' => $entry['name'], 
                    'short' => true,
                ],
                [
                    'title' => 'Anniversary', 
                    'value' => $entry['anniversary'], 
                    'short' => true,
                ],
                [
                    'title' => 'Created', 
                    'value' => $entry['created'], 
                    'short' => true,
                ],
                [
                    'title' => 'Deleted', 
                    'value' => $entry['deleted'], 
                    'short' => true,
                ],
            ],
        ];      
    }

    $webhook_data = [
        'text' => 'Staff changes',
        'attachments' => $attachments,
    ];

    $webhook_url = getenv('SLACK_WEBHOOK');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));

    $result = curl_exec($ch);

    curl_close($ch);
}