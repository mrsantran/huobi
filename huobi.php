<?php

/* ============================================================
 * huobi
 * https://github.com/santran/huobi
 * ============================================================
 * Copyright 2018-, Tran Doan San
 * Released under the MIT License
 * ============================================================ */

namespace Huobi;

class API
{

    protected $base = "", $api_key, $api_secret;
    protected $depthCache = [];
    protected $depthQueue = [];
    protected $info = [];

    public function __construct($api_key = '', $api_secret = '')
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    public function depthCache($symbols, $callback)
    {
        if (!is_array($symbols))
            $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        $connector('wss://api.huobi.pro/ws')->then(function($ws) use($callback) {
            $ws->on('message', function($msg) use($ws, $callback) {
                $data = gzdecode($msg);
                $json = json_decode($data, true);
                if (isset($data['ping'])) {
                    $ws->send(json_encode([
                        "pong" => $data['ping']
                    ]));
                } else {
                    $ch = $json["ch"];
                    $ch = explode(".", $ch);
                    $symbol = strtoupper($ch[1]);
                    $this->depthHandler($json);
                    call_user_func($callback, $this, $symbol, $this->depthCache[$symbol]);
                }
            });
            $ws->on('close', function($code = null, $reason = null) {
                echo "depthCache WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
            });
            foreach ($symbols as $symbol) {
                $datas = json_encode([
                    'sub' => "market.{$symbol}.depth.step5",
                    'id' => "$symbol"
                ]);
                $ws->send($datas);
            }
        }, function($e) use($loop) {
            echo "depthCache Could not connect: {$e->getMessage()}" . PHP_EOL;
            $loop->stop();
        });
        $loop->run();
    }

    private function depthHandler($json)
    {
        $ch = $json["ch"];
        $ch = explode(".", $ch);
        $symbol = strtoupper($ch[1]);
        $tick = $json["tick"];
        foreach ($tick["bids"] as $bid) {
            $this->depthCache[$symbol]['bids'][$bid[0]] = $bid[1];
        }
        foreach ($tick["asks"] as $ask) {
            $this->depthCache[$symbol]['asks'][$ask[0]] = $ask[1];
        }
    }

}
