<?php

declare(strict_types=1);

namespace Netgrity\Core;

final class App
{
    /**
     * @var array<string,mixed>
     */
    private array $config = [];

    public function __construct()
    {
        $this->loadConfiguration();

        date_default_timezone_set(
            $this->config['app']['timezone']
        );
    }

    public function run(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'application' => $this->config['app']['name'],
            'environment' => $this->config['app']['env'],
            'version' => '1.0.0'
        ], JSON_PRETTY_PRINT);
    }

    private function loadConfiguration(): void
    {
        $path = dirname(__DIR__, 2) . '/config/';

        $this->config = [

            'app' => require $path . 'app.php',

            'database' => require $path . 'database.php',

            'whatsapp' => require $path . 'whatsapp.php'

        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function config(string $key): ?array
    {
        return $this->config[$key] ?? null;
    }
}