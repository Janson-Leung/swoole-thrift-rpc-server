<?php
/**
 * Client.php
 *
 * @author Janson
 * @create 2016-10-28
 */

require __DIR__ . "/Thrift/ClassLoader/ThriftClassLoader.php";

use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\TFramedTransport;

$loader = new ThriftClassLoader();
$loader->registerNamespace('Thrift', __DIR__);
$loader->registerNamespace('Services', __DIR__);
$loader->registerDefinition('Services',  __DIR__);
$loader->register();

$socket = new TSocket("127.0.0.1", 8091);
$transport = new TFramedTransport($socket);
$protocol = new TBinaryProtocol($transport);
$transport->open();

$client = new Services\HelloWorld\HelloWorldClient($protocol);
$ret = $client->sayHello('PHPER');
var_dump($ret);

$transport->close();