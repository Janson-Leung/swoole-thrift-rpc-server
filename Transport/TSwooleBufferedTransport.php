<?php
/**
 * TSwooleBufferedTransport.php
 *
 * @author Janson
 * @create 2016-10-28
 */

namespace Transport;

use Thrift\Transport\TBufferedTransport;
use Thrift\Factory\TStringFuncFactory;

class TSwooleBufferedTransport extends TBufferedTransport
{
    public $buffer = '';
    public $server;
    protected $fd;
    protected $read_ = true;
    protected $rBuf_ = '';
    protected $wBuf_ = '';

    public function setHandle($fd)
    {
        $this->fd = $fd;
    }

    public function readAll($len)
    {
        $have = TStringFuncFactory::create()->strlen($this->rBuf_);
        if ($have == 0) {
            $data = $this->readAll($len);
        } elseif ($have < $len) {
            $data = $this->rBuf_;
            $this->rBuf_ = '';
            $data .= $this->readAll($len - $have);
        } elseif ($have == $len) {
            $data = $this->rBuf_;
            $this->rBuf_ = '';
        } elseif ($have > $len) {
            $data = TStringFuncFactory::create()->substr($this->rBuf_, 0, $len);
            $this->rBuf_ = TStringFuncFactory::create()->substr($this->rBuf_, $len);
        }

        return $data;
    }

    public function read($len)
    {
        if (TStringFuncFactory::create()->strlen($this->rBuf_) === 0) {
            $this->rBuf_ = $this->read($this->rBufSize_);
        }

        if (TStringFuncFactory::create()->strlen($this->rBuf_) <= $len) {
            $ret = $this->rBuf_;
            $this->rBuf_ = '';

            return $ret;
        }

        $ret = TStringFuncFactory::create()->substr($this->rBuf_, 0, $len);
        $this->rBuf_ = TStringFuncFactory::create()->substr($this->rBuf_, $len);

        return $ret;
    }

    public function write($buf)
    {
        $this->wBuf_ .= $buf;
    }

    public function flush()
    {
        $out = '';
        if (TStringFuncFactory::create()->strlen($this->wBuf_) > 0) {
            $out = $this->wBuf_;

            // Note that we clear the internal wBuf_ prior to the underlying write
            // to ensure we're in a sane state (i.e. internal buffer cleaned)
            // if the underlying write throws up an exception
            $this->wBuf_ = '';
        }

        $this->server->send($this->fd, $out);
    }
}
