<?php
/**
 * Server.php
 *
 * @author Janson
 * @create 2016-10-28
 */

require __DIR__ . "/Thrift/ClassLoader/ThriftClassLoader.php";
use Thrift\ClassLoader\ThriftClassLoader;

$serv = new \swoole_server('127.0.0.1', 8091);
$serv->set([
    'worker_num'            => 1,
    'dispatch_mode'         => 1, //1: 轮循, 3: 争抢
    'open_length_check'     => true, //打开包长检测
    'package_max_length'    => 8192000, //最大的请求包长度,8M
    'package_length_type'   => 'N', //长度的类型，参见PHP的pack函数
    'package_length_offset' => 0,   //第N个字节是包长度的值
    'package_body_offset'   => 4,   //从第几个字节计算长度
]);


$serv->on('workerStart', function(){
    echo "ThriftServer Start\n";
});

$serv->on('receive', function($serv, $fd, $from_id, $data) {
    $loader = new ThriftClassLoader();
    $loader->registerNamespace('Thrift', __DIR__);
    $loader->registerNamespace('Transport', __DIR__);
    $loader->registerNamespace('Services', __DIR__);
    $loader->registerDefinition('Services',  __DIR__);
    $loader->register();

    $handler = new \Services\HelloWorld\HelloWorldHandler();
    $processor = new \Services\HelloWorld\HelloWorldProcessor($handler);

    $socket = new Transport\TSwooleFramedTransport();
    $socket->setHandle($fd);
    $socket->buffer = $data;
    $socket->server = $serv;

    $protocol = new Thrift\Protocol\TBinaryProtocol($socket, false, false);

    try {
        //$protocol->fname = 'HelloWorld';
        $processor->process($protocol, $protocol);
    } catch (\Exception $e) {
        echo 'CODE:' . $e->getCode() . ' MESSAGE:' . $e->getMessage() . "\n" . $e->getTraceAsString();
    }
});

$serv->start();
