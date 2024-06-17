<?php

declare(strict_types=1);

namespace Qifei\JsonRpc;

use Exception;
use Qifei\JsonRpc\Base\Request;
use Qifei\JsonRpc\Base\Response;
use Qifei\JsonRpc\Base\Rpc;

class Client
{
    public const ERR_RPC_RESPONSE = 'Invalid Response';

    /**
     * The result returned by the server for rpc calls. Will be
     * null if there is an error, for notifications and batches.
     *
     * @var mixed
     */
    public $result;

    /**
     * An array of std objects representing the result or
     * error of batch calls. Will be null for non-batch calls
     * or for batch calls containing only notifications.
     *
     * @var array
     */
    public $batch;

    /**
     * A formatted string of the error:
     *   message (code): [data]
     *
     * This is constructed from either the Json-Rpc error object
     * returned by the server, internally for response or connection errors
     *
     * @var string
     */
    public $error = '';

    /**
     * The error code, either 0 for errors detected in the response
     * from the server or a failed connection, or any of the Json-Rpc
     * errors defined in the specification.
     *
     * @var int
     */
    public $errorCode = 0;

    /**
     * The raw string output returned by the server.
     *
     * @var string
     */
    public $output = '';

    private $url;

    private $transport;

    private $id = 0;

    private $requests = [];

    private $multi = false;

    private $notifications = 0;

    private $headers = [];

    protected $outputData = [];

    protected $ak;

    protected $sk;

    public function __construct($url, $transport = null)
    {
        $this->url = $url;
        $this->transport = $transport;

        if (!$this->transport) {
            $this->transport = new Transport\BasicClient();
        }
    }

    public function addHeaders($value)
    {
        if (is_array($value)) {
            $this->headers = array_merge($this->headers, $value);
        } elseif (is_string($value)) {
            $this->headers[] = $value;
        }
    }

    public function clearHeaders()
    {
        $this->headers = [];
    }

    public function setAk($ak)
    {
        $this->ak = $ak;
        return $this;
    }

    public function setSk($sk)
    {
        $this->sk = $sk;
        return $this;
    }

    public function setTransport($transport)
    {
        $this->transport = $transport;
    }
    public function builder(){
        return $this;
    }
    public function call($method, $params)
    {
        return $this->work($method, [$params]);
    }


    protected function generateSignature($appKey, $appSecret, $data)
    {
        // 使用SHA-256算法计算HMAC
        $signature = hash_hmac('sha256', $data, $appKey . $appSecret, true);
        // 将签名编码为base64字符串
        return base64_encode($signature);
    }

    public function notify($method, $params = null)
    {
        ++$this->notifications;
        return $this->work($method, $params ? [$params] : null, true);
    }

    public function batchOpen()
    {
        $this->resetInput();
        $this->multi = true;
    }

    public function batchSend()
    {
        if (count($this->requests) === 1) {
            $this->resetInput();
            throw new Exception('Batch only has one request');
            return false;
        }

        return $this->send();
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getOutputData()
    {
        return $this->outputData;
    }

    public function getResult()
    {
        return $this->outputData['result'] ?? null;
    }

    private function work($method, $params, $notify = false)
    {
        $data = ['method' => $method];

        if ($params) {
            $data['params'] = $params;
        }
        $rand_num=uniqid();
        $sign=$this->generateSignature($this->ak,$this->sk,json_encode([
            'app_key'=>$this->ak,
            'app_secret'=>$this->sk,
            'time'=>mb_substr((string)time(), 0, 5),
            'rand_number'=>$rand_num,
            'params'=>$params[0],
        ]));
        $this->headers=[
            'app_key'=>$this->ak,
            'time'=>time(),
            'rand_number'=>$rand_num,
            'sign'=>$sign,
        ];
        if (!$notify) {
            if (!$this->id) {
                $this->setId(uniqid());
            }
            $data['id'] = $this->id;
        }

        $request = new Request($data);
        if ($request->fault) {
            throw new Exception($request->fault);
            return false;
        }

        $this->requests[] = $request->toJson();

        if (!$this->multi) {
            return $this->send();
        }
    }

    private function send()
    {
        $this->resetOutput();

        if ($this->multi) {
            $data = '[' . implode(',', $this->requests) . ']';
        } else {
            $data = $this->requests[0];
        }
        try {
            if ($res = $this->transport->send('POST', $this->url, $data, $this->headers)) {
                $this->output = $this->transport->output;
                $this->outputData = json_decode($this->output, true);
                $res = $this->checkResult();
            } else {
                $this->setError($this->transport->error);
            }

            $this->resetInput();

            return (bool)$res;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    private function checkResult()
    {
        $sent = count($this->requests);
        $expected = $sent - $this->notifications;

        if (!$struct = Rpc::decode($this->output, $batch)) {
            if ($expected) {
                $this->setError('Parse error');
            }

            return !$expected;
        }

        if ($res = $this->checkResponses($struct, $batch)) {
            if (!$this->checkReceived($struct, $batch, $expected)) {
                return;
            }

            if ($this->multi) {
                $this->batch = $struct;
            } else {
                if (isset($struct->error)) {
                    $this->setError($struct->error);
                    $res = false;
                } else {
                    $this->result = $struct->result;
                }
            }
        }

        return $res;
    }

    private function checkResponses($json_array, $batch)
    {
        $res = false;

        if ($batch) {
            foreach ($json_array as $item) {
                if (!$res = $this->checkResponse($item)) {
                    break;
                }
            }
        } else {
            $res = $this->checkResponse($json_array);
        }

        return $res;
    }

    private function checkResponse($json_array)
    {
        $response = new Response();

        if (!$res = $response->create($json_array)) {
            $this->setError($response->fault);
        }

        return $res;
    }

    private function checkReceived(&$struct, $batch, $expected)
    {
        $received = $batch ? count($struct) : 1;

        if ($received !== $expected || $batch !== $this->multi) {
            $ok = isset($struct->error) && $struct->error->code === Rpc::ERR_PARSE;

            if ($ok && $received === 1) {
                $error = 'Response reports Parse error (' . Rpc::ERR_PARSE . ')';
            } else {
                $error = 'Mismatched responses';
            }

            $this->setError($error);
            return false;
        }

        return $batch ? $this->checkBatch($struct, $expected) : true;
    }

    private function checkBatch(&$struct, $expected)
    {
        if ($res = $this->orderResponses($struct)) {
            for ($i = 0; $i < $expected; ++$i) {
                if ($struct[$i]->id !== $i + 1) {
                    $this->setError('Duplicate response id');
                    $res = false;
                    break;
                }
            }
        }

        return $res;
    }

    private function orderResponses(&$struct)
    {
        if (is_array($struct)) {
            try {
                usort($struct, [$this, 'order']);
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }

        return true;
    }

    private function order($a, $b)
    {
        if ($a->id == $b->id) {
            throw new Exception('Duplicate response ids');
        }

        return ($a->id < $b->id) ? -1 : 1;
    }

    private function setError($error)
    {
        if (is_string($error)) {
            $code = 0;
            $message = static::ERR_RPC_RESPONSE;
            $data = $error;
        } else {
            $code = $error->code;
            $message = $error->message;
            $data = isset($error->data) ? $error->data : null;
        }

        $data = $data ? ': ' . $data : '';

        $this->error = $message . " ({$code})" . $data;
        $this->errorCode = $code;
    }

    private function resetInput()
    {
        $this->id = 0;
        $this->requests = [];
        $this->multi = false;
        $this->notifications = 0;
    }

    private function resetOutput()
    {
        $this->result = null;
        $this->batch = null;
        $this->error = '';
        $this->errorCode = 0;
        $this->output = '';
    }
}
