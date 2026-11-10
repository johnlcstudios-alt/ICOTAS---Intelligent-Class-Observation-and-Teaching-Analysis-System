<?php

namespace App\Core;

class HttpException extends \RuntimeException
{
    private int $status;

    public function __construct(int $status = 404, string $message = '')
    {
        $this->status = $status;
        parent::__construct($message !== '' ? $message : self::defaultMessage($status));
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        $messages = [
            400 => 'The request could not be processed.',
            401 => 'Please sign in to continue.',
            403 => 'You are not authorized to access this resource.',
            404 => 'The page you are looking for could not be found.',
            419 => 'Your session or security token has expired. Please refresh and try again.',
            422 => 'The submitted information could not be validated.',
            429 => 'Too many requests. Please wait a moment and try again.',
            500 => 'An unexpected system error occurred.',
            503 => 'The system is temporarily unavailable.',
        ];
        return $messages[$status] ?? 'Request failed.';
    }
}
