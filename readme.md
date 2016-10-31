# Thrift RPC Server

## 流程

	client -> swoole receive -> thrift -> service -> thrfit -> swoole send -> client

## 服务端命令

	thrift --gen php:server xxxx.thrift

## Thrift类库

  1. 数据传输格式（protocol）是定义的了传输内容，对Thrift Type的打包解包，包括

    - TBinaryProtocol，二进制格式，TBinaryProtocolAccelerated则是依赖于thrift_protocol扩展的快速打包解包。
    - TCompactProtocol，压缩格式
    - TJSONProtocol，JSON格式
    - TMultiplexedProtocol，利用前三种数据格式与支持多路复用协议的服务端（同时提供多个服务，TMultiplexedProcessor）交互
  
  2. 数据传输方式（transport），定义了如何发送（write）和接收（read）数据，包括

    - TBufferedTransport，缓存传输，写入数据并不立即开始传输，直到刷新缓存。
    - TSocket，使用socket传输
    - TFramedTransport，采用分块方式进行传输，具体传输实现依赖其他传输方式，比如TSocket
    - TCurlClient，使用curl与服务端交互
    - THttpClient，采用stream方式与HTTP服务端交互
    - TMemoryBuffer，使用内存方式交换数据
    - TPhpStream，使用PHP标准输入输出流进行传输
    - TNullTransport，关闭数据传输
    - TSocketPool在TSocket基础支持多个服务端管理（需要APC支持），自动剔除无效的服务器
    - TNonblockingSocket，非官方实现非阻塞socket
   
  3. 服务模型，定义了当PHP作为服务端如何监听端口处理请求

    - TForkingServer，采用子进程处理请求
    - TSimpleServer，在TServerSocket基础上处理请求
    - TNonblockingServer，基于libevent的非官方实现非阻塞服务端，与TNonblockingServerSocket，TNonblockingSocket配合使用
    
  4. 另外还定义了一些工厂，以便在Server模式下对数据传输格式和传输方式进行绑定

    - TProtocolFactory，数据传输格式工厂类，对protocol的工厂化生产，包括TBinaryProtocolFactory，TCompactProtocolFactory，TJSONProtocolFactory
    - TTransportFactory，数据传输方式工厂类，对transport的工厂化生产，作为server时，需要自行实现
    - TStringFuncFactory，字符串处理工厂类

## 目录结构

  - Services：中间文件、服务文件
  - Thrift：thrift类库文件
  - Transport：thrift传输转swoole传输文件

## 编码说明

  1. 服务端的服务实现代码则需要继承xxxxIf接口实现代码
  2. 连接、接收由swoole接管，thrift处理主要在swoole receive回调中
