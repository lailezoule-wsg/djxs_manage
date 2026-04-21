<?php
declare(strict_types=1);

namespace app\common\exception;

class BizException extends \RuntimeException
{
    private int $httpCode;
    private int $bizCode;
    private array $data;

    public function __construct(
        string $message,
        int $httpCode = 400,
        int $bizCode = 40001,
        array $data = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->bizCode = $bizCode;
        $this->data = $data;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getBizCode(): int
    {
        return $this->bizCode;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
