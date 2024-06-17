<?php

declare(strict_types=1);

namespace Qifei\JsonRpc\Transport;

class BasicServer
{
    public function receive()
    {
        return @file_get_contents('php://input');
    }

    public function reply($data)
    {
        echo $data;
        exit;
    }
}
