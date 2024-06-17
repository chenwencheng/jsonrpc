<?php

declare(strict_types=1);

namespace Qifei\JsonRpc\Base;

use Exception;

class Rpc
{
    public const ERR_PARSE = -32700;

    public const ERR_REQUEST = -32600;

    public const ERR_METHOD = -32601;

    public const ERR_PARAMS = -32602;

    public const ERR_INTERNAL = -32603;

    public const ERR_SERVER = -32000;

    public const MODE_CHECK = 0;

    public const MODE_GET = 1;

    public const MODE_EXISTS = 2;

    public $fault = '';

    protected $jsonrpc = '2.0';

    protected $id;

    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
    }

    public static function decode($message, &$batch)
    {
        $struct = @json_decode($message, false);
        $batch = is_array($struct);

        return $struct;
    }

    public static function getErrorMsg($name, $exists = true)
    {
        if ($name) {
            if ($exists) {
                return 'Invalid value for: ' . $name;
            }

            return 'Missing member: ' . $name;
        }

        return 'Invalid structure';
    }

    protected function check($name, $value, $exists)
    {
        $res = false;

        if ($exists) {
            switch ($name) {
                case 'jsonrpc':
                    $res = $value === $this->jsonrpc;
                    break;
                case 'method':
                    $res = is_string($value) && $value;
                    break;
                case 'params':
                    $res = is_array($value) || is_object($value);
                    break;
                case 'id':
                    $res = $this->checkId($value);
                    break;
                case 'result':
                    $res = true;
                    break;
                case 'error':
                    $res = $this->checkError($value);
                    break;
            }
        }

        if (! $res) {
            throw new Exception($this->getErrorMsg($name, $exists));
        }

        return $value;
    }

    protected function get($container, $key, $mode = 0)
    {
        $exists = false;
        $value = null;

        if (is_array($container)) {
            $exists = array_key_exists($key, $container);
            $value = $exists ? $container[$key] : null;
        } elseif (is_object($container)) {
            $exists = property_exists($container, $key);
            $value = $exists ? $container->{$key} : null;
        }

        if ($mode === static::MODE_GET) {
            return $value;
        }
        if ($mode === static::MODE_EXISTS) {
            return $exists;
        }

        return $this->check($key, $value, $exists);
    }

    protected function setVersion($struct, $new)
    {
        if (! $new) {
            $this->jsonrpc = $this->get($struct, 'jsonrpc');
        }
    }

    private function checkError($error)
    {
        if (! is_array($error)) {
            $error = (array) $error;
        }

        $code = isset($error['code']) ? $error['code'] : null;
        $message = isset($error['message']) ? $error['message'] : null;

        $allowed = [-32700, -32600, -32601, -32602, -32603];

        if (! in_array($code, $allowed)) {
            $max = -32000;
            $min = -32099;

            if ($code < $min || $code > $max) {
                return;
            }
        }

        return is_int($code) && $code && is_string($message);
    }

    private function checkId($id)
    {
        if ((is_string($id) && $id) || is_int($id)) {
            return true;
        }
        if (! is_null($id)) {
            return false;
        }

        $allowNull = false;

        if (isset($this->error)) {
            $code = $this->get($this->error, 'code', static::MODE_GET);
            $allowNull = $code === static::ERR_PARSE || $code === static::ERR_REQUEST;
        }

        return $allowNull;
    }
}
