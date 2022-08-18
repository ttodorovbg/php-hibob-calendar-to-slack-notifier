<?php

declare(strict_types=1);

namespace App;


/**
 * Undocumented class
 */
class Slack
{
    /**
     * Undocumented function
     *
     * @param array $data 
     * 
     * @return void
     */
    public static function notify(array $data)
    {
        $blocks = [];

        foreach (['changes', 'birthday', 'holiday'] as $index) {

            if (!isset($data[$index]) 
                || !is_array($data[$index]) 
                || !count($data[$index])
            ) {
                continue;
            }
            
            foreach ($data[$index] as $entry) {

                $prefix = match ($index) {
                    'birthday' => ':birthday:',
                    'changes' => $entry['deleted'] ? 
                        ':heavy_minus_sign:' : 
                        ':heavy_plus_sign:',
                    'holiday' => ':palm_tree:',
                };

                $blocks[] = [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'plain_text',
                            'text' => $prefix,
                            'emoji' => true,
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "Name: *{$entry['name']}* " . 
                                (
                                    !empty($entry['anniversary']) ? 
                                    "Anniv: *{$entry['anniversary']}* " : 
                                        ''
                                ) . 
                                (
                                    !empty($entry['birthday']) ? 
                                        "B-day: *{$entry['birthday']}* " : 
                                        ''
                                ) . 
                                (
                                    !empty($entry['created']) ? 
                                        "Cre: *{$entry['created']}* " : 
                                        ''
                                ) . 
                                (
                                    !empty($entry['deleted']) ? 
                                        "Del: *{$entry['deleted']}*" : 
                                        ''
                                ),
                        ],
                    ],
                ];      
            }
        }        

        if (!count($blocks)) {
            return;
        }

        $webhook_data = [        
            'text' => 'Staff Notification',
            'blocks' => $blocks,
        ];

        //print_r(strlen(json_encode($webhook_data)));
        
        $webhook_url = getenv('SLACK_WEBHOOK_URL');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));

        $response = curl_exec($ch);

        print_r($response);

        curl_close($ch);
    }    
}