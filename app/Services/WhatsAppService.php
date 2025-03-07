<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send a WhatsApp message.
     */
    public static function sendMessage($receiverNumber, $message, $file = null): array
    {
        try {
            $client = new Client();

            $formParams = [
                'appkey' => env('WHATSAPP_APP_KEY'),
                'authkey' => env('WHATSAPP_APP_SECRET'),
                'to' => $receiverNumber,
                'message' => $message,
                'sandbox' => 'false',
            ];

            if ($file) {
                $formParams['file'] = $file;
            }

            $response = $client->post(env('WHATSAPP_APP_URL'), [
                'form_params' => $formParams,
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3, // Use the appropriate SSL version
                ],
                'timeout' => 10, // Set a timeout to prevent hanging requests
            ]);

            $responseBody = $response->getBody()->getContents();

            // Log successful requests
            Log::info("WhatsApp message sent to {$receiverNumber}: " . json_encode($responseBody));

            return [
                'success' => true,
                'data' => json_decode($responseBody, true),
            ];
        } catch (ConnectException $e) {
            Log::error("WhatsApp API Connection Error: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Connection error. Please check your network or API URL.',
            ];
        } catch (RequestException $e) {
            $errorResponse = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("WhatsApp API Request Error: " . $errorResponse);

            return [
                'success' => false,
                'error' => 'API request failed: ' . $errorResponse,
            ];
        } catch (\Exception $e) {
            Log::error("WhatsApp Service Error: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again later.',
            ];
        }
    }
}
