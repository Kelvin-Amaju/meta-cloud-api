<?php

require_once APP_PATH . '/Services/WhatsAppService.php';

class WhatsAppController
{
    private WhatsAppService $service;

    public function __construct()
    {
        $config = require APP_PATH . '/../config.php';

        $this->service = new WhatsAppService($config);
    }

    public function send()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $phone = trim($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? '');

        $allowReply = isset($_POST['allow_reply']) ? 1 : 0;

        if ($phone === '' || $message === '') {
            $_SESSION['error'] = 'Phone number and message are required.';
            header('Location: /whatsapp/send');
            exit;
        }

        try {

            $response = $this->service->sendText($phone, $message);

            if ($response['status'] == 200) {

                $_SESSION['success'] = 'Message sent successfully.';

            } else {

                $_SESSION['error'] =
                    $response['body']['error']['message']
                    ?? 'Unable to send message.';
            }

        } catch (Exception $e) {

            $_SESSION['error'] = $e->getMessage();

        }

        header('Location: /whatsapp/send');
        exit;
    }
}