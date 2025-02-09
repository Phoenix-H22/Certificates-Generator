<?php

namespace App\Services;

use GuzzleHttp\Client;

class WhatsAppService
{

    /**
     * Execute the job.
     */
    public static function sendMessage($receiverNumber, $message,$file = null): string
    {
        $client = new Client();
        $formParams = [
            'appkey' => env('WHATSAPP_APP_KEY'),
            'authkey' => env('WHATSAPP_APP_SECRET'),
            'to' => $receiverNumber,
            'message' => $message,
            'sandbox' => 'false',
        ];
// Attach file URL if provided
        if ($file) {
            $formParams['file'] = $fileUrl;
        }
        $response = $client->post(env('WHATSAPP_APP_URL'), [
            'form_params' => $formParams,
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3, // Use the appropriate SSL version
            ],
        ]);

        return $response->getBody()->getContents();
    }

}