<?php

declare(strict_types=1);

namespace Qifei\JsonRpc\Transport;

use Hyperf\Validation\Contract\ValidatorAwareRule;

class BasicClient
{
    public $output = '';

    public $error = '';

    public function send($method, $url, $json, $headers = [])
    {
        foreach($headers as $k=>$v) {
            $headers[] = $k.': '.$v;
            unset($headers[$k]);
        }
        $header = 'Content-Type: application/json';

        if (! in_array($header, $headers)) {
            $headers[] = $header;
        }
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $json,
            ],
        ];

        $context = stream_context_create($opts);
        var_dump($context);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->error = 'Unable to connect to ' . $url;
            return;
        }

        $this->output = $response;

        return true;
    }
}
