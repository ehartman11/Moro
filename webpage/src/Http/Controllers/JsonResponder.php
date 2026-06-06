<?php
declare(strict_types=1);

namespace Moro\Http\Controllers;

use Moro\Core\Response;

final class JsonResponder
{
    public static function send(array $payload, int $status = 200): never
    {
        Response::json($payload, $status);
    }

    public static function error(string $error, int $status = 400): never
    {
        self::send([
            'ok' => false,
            'error' => $error,
        ], $status);
    }
}