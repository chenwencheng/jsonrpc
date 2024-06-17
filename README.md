# install
```
composer require qifei/jsonrpc
```

# usage
```
use Qifei\JsonRpc\Client;

$request_id='xxxx';
$client=(new Client('http://xxx.com'))->setAk('xxxxxxxxxxxxak')
          ->setSk('xxxxxxxxxxxxxxxxxxxsk')->builder();
$client->setId($request_id)->call('/xx/xxx',[
            'xx'=>"xxxxMw6imMjCA9vss"
        ]);
$res=$client->getResult();
var_dump($res);
```