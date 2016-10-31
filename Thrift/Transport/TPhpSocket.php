<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements. See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership. The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package thrift.transport
 */

namespace Thrift\Transport;

use Thrift\Exception\TException;
use Thrift\Exception\TTransportException;
use Thrift\Factory\TStringFuncFactory;

/**
 * Sockets implementation of the TTransport interface.
 *
 * @package thrift.transport
 */
class TPhpSocket extends TTransport {
	/**
	 * Handle to PHP socket
	 *
	 * @var resource
	 */
	private $handle_ = null;

	/**
	 * Remote hostname
	 *
	 * @var string
	 */
	protected $host_ = 'localhost';

	/**
	 * Remote port
	 *
	 * @var int
	 */
	protected $port_ = '9090';

	/**
	 * Send timeout in seconds.
	 *
	 * Combined with sendTimeoutUsec this is used for send timeouts.
	 *
	 * @var int
	 */
	private $sendTimeoutSec_ = 0;

	/**
	 * Send timeout in microseconds.
	 *
	 * Combined with sendTimeoutSec this is used for send timeouts.
	 *
	 * @var int
	 */
	private $sendTimeoutUsec_ = 100000;

	/**
	 * Recv timeout in seconds
	 *
	 * Combined with recvTimeoutUsec this is used for recv timeouts.
	 *
	 * @var int
	 */
	private $recvTimeoutSec_ = 0;

	/**
	 * Recv timeout in microseconds
	 *
	 * Combined with recvTimeoutSec this is used for recv timeouts.
	 *
	 * @var int
	 */
	private $recvTimeoutUsec_ = 750000;

	/**
	 * Persistent socket or plain?
	 *
	 * @var bool
	 */
	protected $persist_ = false;

	/**
	 * Debugging on?
	 *
	 * @var bool
	 */
	protected $debug_ = false;

	/**
	 * Debug handler
	 *
	 * @var mixed
	 */
	protected $debugHandler_ = null;

	/**
	 * Socket constructor
	 *
	 * @param string $host         Remote hostname
	 * @param int    $port         Remote port
	 * @param bool   $persist      Whether to use a persistent socket
	 * @param string $debugHandler Function to call for error logging
	 */
	public function __construct($host='localhost', $port=9090, $persist=false, $debugHandler=null) {
		$this->host_ = $host;
		$this->port_ = $port;
		$this->persist_ = $persist;
		$this->debugHandler_ = $debugHandler ? $debugHandler : 'error_log';
	}

	/**
	 * @param resource $handle
	 * @return void
	 */
	public function setHandle($handle) {
		$this->handle_ = $handle;
	}

	/**
	 * Sets the send timeout.
	 *
	 * @param int $timeout	Timeout in milliseconds.
	 */
	public function setSendTimeout($timeout) {
		$this->sendTimeoutSec_ = floor($timeout / 1000);
		$this->sendTimeoutUsec_ = ($timeout - ($this->sendTimeoutSec_ * 1000)) * 1000;
	}

	/**
	 * Sets the receive timeout.
	 *
	 * @param int $timeout	Timeout in milliseconds.
	 */
	public function setRecvTimeout($timeout) {
		$this->recvTimeoutSec_ = floor($timeout / 1000);
		$this->recvTimeoutUsec_ = ($timeout - ($this->recvTimeoutSec_ * 1000)) * 1000;
	}

	/**
	 * Sets debugging output on or off
	 *
	 * @param bool $debug
	 */
	public function setDebug($debug) {
		$this->debug_ = $debug;
	}

	/**
	 * Get the host that this socket is connected to
	 *
	 * @return string host
	 */
	public function getHost() {
		return $this->host_;
	}

	/**
	 * Get the remote port that this socket is connected to
	 *
	 * @return int port
	 */
	public function getPort() {
		return $this->port_;
	}

	/**
	 * Tests whether this is open
	 *
	 * @return bool true if the socket is open
	 */
	public function isOpen() {
		return is_resource($this->handle_);
	}

	/**
	 * Connects the socket.
	 */
	public function open() {
		if ($this->isOpen()) {
			throw new TTransportException('TPhpSocket: Socket already connected', TTransportException::ALREADY_OPEN);
		}

		if (empty($this->host_)) {
			throw new TTransportException('TPhpSocket: Cannot open null host', TTransportException::NOT_OPEN);
		}

		if ($this->port_ <= 0) {
			throw new TTransportException('TPhpSocket: Cannot open without port', TTransportException::NOT_OPEN);
		}

		$this->handle_ = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		// Create Failed?
		if ($this->handle_ === FALSE) {
			$error = "TPhpSocket: Failed to create to {$this->host_}:{$this->port_}";
			if ($this->debug_) {
				call_user_func($this->debugHandler_, $error);
			}
			throw new TException($error);
		}

		if (@socket_connect($this->handle_, $this->host_, $this->port_) === false) {
			$this->error("TPhpSocket: Failed to connect socket to {$this->host_}:{$this->port_}");
		}

		if (@socket_set_option($this->handle_, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>$this->recvTimeoutSec_, 'usec'=>$this->recvTimeoutUsec_]) === false) {
			$this->error("TPhpSocket: Failed to set socket stream recv timeout option");
		}

		if (@socket_set_option($this->handle_, SOL_SOCKET, SO_SNDTIMEO, ['sec'=>$this->sendTimeoutSec_, 'usec'=>$this->sendTimeoutUsec_]) === false) {
			$this->error("TPhpSocket: Failed to set socket stream send timeout option");
		}
	}

	/**
	 * Closes the socket.
	 */
	public function close() {
		if ( ! $this->persist_) {
			if (is_resource($this->handle_)) {
				@socket_shutdown($this->handle_);
				@socket_close($this->handle_);
			}
			$this->handle_ = null;
		}
	}

	/**
	 * Read from the socket at most $len bytes.
	 *
	 * This method will not wait for all the requested data, it will return as
	 * soon as any data is received.
	 *
	 * @param int $len Maximum number of bytes to read.
	 * @return string Binary data
	 */
	public function read($len) {
		$data = @socket_read($this->handle_, $len, PHP_NORMAL_READ);
		if ($data === false) {
			$this->error("TPhpSocket: Failed to read data from {$this->host_}:{$this->port_}");
		}

		return $data;
	}

	/**
	 * Write to the socket.
	 *
	 * @param string $buf The data to write
	 */
	public function write($buf) {
		$written = 0;
		$length = TStringFuncFactory::create()->strlen($buf);

		while($written < $length) {
			$fwrite = @socket_write($this->handle_, TStringFuncFactory::create()->substr($buf, $written));
			if ($fwrite === false) {
				$this->error("TPhpSocket: Failed to write buffer to {$this->host_}:{$this->port_}");
			}

			$written += $fwrite;
		}
	}

	/**
	 * Flush output to the socket.
	 *
	 * Since read(), readAll() and write() operate on the sockets directly,
	 * this is a no-op
	 *
	 * If you wish to have flushable buffering behaviour, wrap this TSocket
	 * in a TBufferedTransport.
	 */
	public function flush() {
		// no-op
	}

	/**
	 * Fail with socket error
	 *
	 * @param string $msg
	 * @throws TTransportException
	 */
	private function error($msg) {
		$errmsg = @socket_strerror($errno = socket_last_error($this->handle_));
		throw new TTransportException("{$errmsg} -> {$msg}", $errno);
	}
}