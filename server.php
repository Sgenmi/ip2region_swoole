<?php

/**
 * Description of HttpServer
 *
 * @author Sgenmi
 * @date 2016-11-21
 * @email 150560159@qq.com
 */
class HttpServer {

    public static $instance;
    public $http;
    public static $get;
    public static $header;
    public static $server;
    public static $ipData;
    public static $ipOption;

    public function __construct() {
        $http = new Swoole\Http\Server("0.0.0.0", 7070, SWOOLE_PROCESS);
        $http->set(
                array(
                    'worker_num' => 4,
                    'open_cpu_affinity' => 4,
//                    'daemonize' => TRUE,
                    'daemonize' => FALSE,
                    'max_request' => 1000000,
//                    'dispatch_mode' => 1,
                    'task_worker_num' => 20,
//                    'log_file' => APP . '/data/ip2region.log',
                    'backlog' => 1024
                )
        );
        $http->on('WorkerStart', array($this, 'onWorkerStart'));
        $http->on('request', array($this, 'onRequest'));
        $http->on("task", array($this, "onTask"));
        $http->on("finish", array($this, "onFinish"));
        $this->http = $http;
        $http->start();
    }

    public function onRequest(Swoole\Http\Request $request, Swoole\Http\Response $response) {
        if (isset($request->header)) {
            HttpServer::$header = $request->header;
        } else {
            HttpServer::$header = [];
        }
        if (isset($request->get)) {
            HttpServer::$get = $request->get;
        } else {
            HttpServer::$get = [];
        }

        if (isset(HttpServer::$get['ac']) && HttpServer::$get['ac'] == 'reload') {
            $this->http->reload();
            return;
        }

        if (!isset(HttpServer::$get['ip'])) {
            $ret = array();
        } else {
            $ip = HttpServer::$get['ip'];
            $ret = $this->http->taskwait($ip);
        }
        $response->status(200);
        $response->write(json_encode($ret));
        $response->end();
    }

    public function onTask($serv, $taskId, $fromId, $ip) {
        if (is_string($ip)) {
            $ip = self::safeIp2long($ip);
        }
        //binary search to define the data
        $l = 0;
        $h = HttpServer::$ipOption['totalBlocks'];
        $dataPtr = 0;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);
            $p = HttpServer::$ipOption['firstIndexPtr'] + $m * INDEX_BLOCK_LENGTH;
            $sip = self::getLong(HttpServer::$ipData, $p);
            if ($ip < $sip) {
                $h = $m - 1;
            } else {
                $eip = self::getLong(HttpServer::$ipData, $p + 4);
                if ($ip > $eip) {
                    $l = $m + 1;
                } else {
                    $dataPtr = self::getLong(HttpServer::$ipData, $p + 8);
                    break;
                }
            }
        }
        //not matched just stop it here
        if ($dataPtr == 0) {
            return FALSE;
        }
        //get the data
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);
        return array(
            'city_id' => self::getLong(HttpServer::$ipData, $dataPtr),
            'region' => substr(HttpServer::$ipData, $dataPtr + 4, $dataLen - 4)
        );
    }

    function onFinish($serv, $taskId, $data) {
        return $data;
    }

    public function onWorkerStart() {
        define("BASE_PATH", __DIR__ . "/");
        define('INDEX_BLOCK_LENGTH', 12);
        HttpServer::$ipData = file_get_contents(BASE_PATH . "/data/ip2region.db");
        if (!HttpServer::$ipData) {
            exit("加载数据失败");
        }
        HttpServer::$ipOption['firstIndexPtr'] = self::getLong(HttpServer::$ipData, 0);
        HttpServer::$ipOption['lastIndexPtr'] = self::getLong(HttpServer::$ipData, 4);
        $index = HttpServer::$ipOption['lastIndexPtr'] - HttpServer::$ipOption['firstIndexPtr'];
        HttpServer::$ipOption['totalBlocks'] = $index / INDEX_BLOCK_LENGTH + 1;
    }

    /**
     * read a long from a byte buffer
     *
     * @param    b
     * @param    offset
     */
    public static function getLong($b, $offset) {
        $val = (
                (ord($b[$offset++])) |
                (ord($b[$offset++]) << 8) |
                (ord($b[$offset++]) << 16) |
                (ord($b[$offset]) << 24)
                );

        // convert signed int to unsigned int if on 32 bit operating system
        if ($val < 0 && PHP_INT_SIZE == 4) {
            $val = sprintf("%u", $val);
        }

        return $val;
    }

    /**
     * safe self::safeIp2long function 
     *
     * @param ip 
     * */
    public static function safeIp2long($ip) {
        $ip = ip2long($ip);
        // convert signed int to unsigned int if on 32 bit operating system
        if ($ip < 0 && PHP_INT_SIZE == 4) {
            $ip = sprintf("%u", $ip);
        }

        return $ip;
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new HttpServer;
        }
        return self::$instance;
    }

}

HttpServer::getInstance();
