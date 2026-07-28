<?php

class WhatsAppService
{
    private string $apiVersion = 'v23.0';
    private string $phoneNumberId;
    private string $accessToken;

    public function __construct(array $config)
    {
        $this->phoneNumberId = $config['phone_number_id'];
        $this->accessToken   = $config['access_token'];
    }

    private function request(string $method, string $endpoint, array $payload = [])
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$endpoint}";

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->accessToken}",
                "Content-Type: application/json"
            ]
        ]);

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'status' => $status,
            'body'   => json_decode($response, true)
        ];
    }

    public function sendText(string $to, string $message)
    {
        return $this->request(
            'POST',
            "{$this->phoneNumberId}/messages",
            [
                "messaging_product" => "whatsapp",
                "recipient_type" => "individual",
                "to" => $to,
                "type" => "text",
                "text" => [
                    "preview_url" => false,
                    "body" => $message
                ]
            ]
        );
    }
}