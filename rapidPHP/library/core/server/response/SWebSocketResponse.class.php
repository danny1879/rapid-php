<?php

namespace rapidPHP\library\core\server\response;

use rapidPHP\library\core\server\Request;
use rapidPHP\library\core\server\Response;
use Swoole\WebSocket\Server;

class SWebSocketResponse extends Response
{

    /**
     * @var int
     */
    private $fd;

    /**
     * @var Server
     */
    private $server;

    /**
     * @return mixed
     */
    public function getFd()
    {
        return $this->fd;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * SWebSocketResponse constructor.
     * @param string|null $sessionId
     * @param Server $server
     * @param $fd
     */
    public function __construct(?string $sessionId, Server $server, $fd)
    {
        parent::__construct($sessionId);
        $this->server = $server;
        $this->fd = $fd;
    }

    /**
     * 快速获取实例对象
     * @param string|null $sessionId
     * @param Server $response
     * @param $fd
     * @return Response
     */
    public static function getInstance(?string $sessionId, Server $response, $fd)
    {
        return new self($sessionId, $response, $fd);
    }

    /**
     * 此方法模拟http发送header status
     * @param $code
     * @return bool
     */
    public function status($code): bool
    {
        return $this->write("Header-Status: $code");
    }

    /**
     * 此方法模拟http发送header
     * @param $data
     * @param bool $ucfirst
     * @return bool
     */
    public function header($data, $ucfirst = true): bool
    {
        $data = explode(":", $data);

        $key = B()->getData($data, 0);

        $value = trim(B()->getData($data, 1));

        return $this->write("Header-{$key}: {$value}");
    }

    /**
     * 重定向
     * @param $url
     * @param int $httpCode
     * @return bool
     */
    public function redirect($url, $httpCode = 302): bool
    {
        $this->status($httpCode);

        return $this->header("Location", $url);
    }

    /**
     * 此方法模拟http发送cookie
     *
     * @param string $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite 从 v4.4.6 版本开始支持
     * @return bool
     */
    public function cookie($key, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false, $samesite = ''): bool
    {
        return $this->write(self::getCookieString($key, $value, $expire, $path, $domain, $secure, $httponly, $samesite));
    }

    /**
     * 获取生成cookie的字符串
     * @param $key
     * @param $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite
     * @return string
     */
    public static function getCookieString($key, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false, $samesite = ''): string
    {
        $cookie = ["{$key}={$value}"];

        if ($expire != 0) $cookie[] = "expires=" . gmdate(DATE_RFC822, $expire);
        if (!empty($path)) $cookie[] = "path={$path}";
        if (!empty($domain)) $cookie[] = "domain={$domain}";
        if (!empty($secure)) $cookie[] = "secure";
        if (!empty($httponly)) $cookie[] = "httponly";
        if (!empty($samesite)) $cookie[] = "samesite={$samesite}";

        return "Header-Set-Cookie: " . join(";", $cookie);
    }

    /**
     * 给客户端发送数据
     *
     * @param string $data
     * @param array $options
     * @return bool
     */
    public function write($data, $options = []): bool
    {
        if (empty($data)) return false;

        $binary_data = B()->getData($options, 'binary_data');
        if (is_null($binary_data)) $binary_data = true;

        $finish = B()->getData($options, 'finish');
        if (is_null($finish)) $finish = true;

        return $this->server->push($this->fd, $data, $binary_data, $finish);
    }

    /**
     * 发送文件
     * @param string $filename
     * @param array $options
     * @return bool
     */
    public function sendFile($filename, $options = []): bool
    {
        $start = (int)B()->getData($options, 'start');

        $end = (int)B()->getData($options, 'end');

        return $this->server->sendFile($this->fd, $filename, $start, max(0, $end - $start));
    }

    /**
     * 次方法只是发送数据，并不是真正的技术，请自行调用disconnect
     * @param string $data
     * @param array $options
     * @return bool
     */
    public function end($data = '', $options = []): bool
    {
        if (empty($data)) return false;

        return $this->write($data, $options);
    }
}