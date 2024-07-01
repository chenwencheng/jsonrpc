<?php
namespace Qifei\JsonRpc\Transport;

class BasicClient
{
    public $output = '';

    public $error = '';

    protected $timeout = 3;

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

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
                'timeout' => $this->timeout,
            ],
        ];

        $context = stream_context_create($opts);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->error = 'Unable to connect to ' . $url;
            return;
        }

        $this->output = $response;

        return true;
    }
}
