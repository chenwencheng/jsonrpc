# install
```
composer require qifei/jsonrpc
```

# usage
```
use Qifei\JsonRpc\Client;

$client = new Client($url);
$client->call('method', $params);
// or
$client->setId(uniqid())->call('method', $params);
$client->getResult();
```