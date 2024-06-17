# install
```
composer require qifei/jsonrpc
```

# usage
```
$endpoint='http:xxxx.com';
$ak='your app_key';
$sk='your app_secret';
$client = (new Client($endpoint))
       ->setAk($ak)
       ->setSk($sk)
       ->builder();

$client->setId('123123')->call('/xxx/xxx', [
       "username"=>'xiaoming',
       'password =>'123456
]);

$res=$client->getResult();
var_dump($res);
```