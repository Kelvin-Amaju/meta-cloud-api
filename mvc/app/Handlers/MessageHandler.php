<?php

class MessageHandler
{
    public static function handle(array $event)
    {
        switch ($event['type']) {

            case 'text':
                self::text($event);
                break;

            case 'image':
                self::image($event);
                break;

            case 'document':
                self::document($event);
                break;

            case 'audio':
                self::audio($event);
                break;

            default:

                file_put_contents(
                    APP_PATH . '/../storage/logs/events.log',
                    "Unknown event: "
                    . json_encode($event)
                    . PHP_EOL,
                    FILE_APPEND
                );

        }
    }

    private static function text(array $event)
    {
        // save to database
    }

    private static function image(array $event)
    {
        // save image metadata
    }

    private static function document(array $event)
    {
    }

    private static function audio(array $event)
    {
    }
}