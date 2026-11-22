<?php

namespace App\Lib\Http;

class Response
{
    public int $statusCode = 200;
    public string $content = '';

    public function json(array $data, int $statusCode): self
    {
        $this->statusCode = $statusCode;
        $this->content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $this;
    }
}
