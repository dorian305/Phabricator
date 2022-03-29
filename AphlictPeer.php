<?php

require_once __DIR__ . '/vendor/autoload.php';

class AphlictPeer {

    /* PROPERTIES */
    public $host;
    public $port;
    public $protocol;
    public $fingerprint;
    /* */

    // Constructor
    function __construct($host, $port, $protocol){
        $this->host = $host;
        $this->port = $port;
        $this->protocol = $protocol;
    }

    /* METHODS */
    // Get fingerprint
    public function getFingerprint(){
        return $this->fingerprint;
    }
    // Broadcast messtage
    public function broadcastMessage($instance, $message){
        $data;
        try {
            $data = json_encode($message);
        } catch (Exception $e){
            echo "Exception: {$e->getMessage()}\n";
            return;
        }

        $options = [
            "hostname" => $this->host,
            "port" => $this->port,
            "method" => "POST",
            "path" => "/?instance={$instance}",
            "headers" => [
                "Content-Type" => "application/json",
                "Content-Length" => strlen($data),
            ],
        ];

        //Need to bind a method onResponse to this class which I have no idea how to do
        // AphlictPeer::onResponse($this, $response); => $this->onResponse($response)
        $onresponse = function ($response){
            $this->onResponse($response);
        };

        $request;
        if ($this->protocol === 'https'){
            Swoole\Coroutine\Http\Client($this->host, $this->port, true)
            $request = // https request
        }
        else {
            $request = // isto ko i gore
        }
        
        // restrukturirati u skladu s async/await

        $request
        
    }

    public function onResponse($response){
        $peer = $this;
        $data = "";

        // zapravo je server
        $response->on('Message', function(Server $server, Frame $bytes)
        use ($data) {
            $data .= $bytes;
        });
        
        $response->on('Close', function(Server $server, int $fd)
        use ($data) {
            // exception handling??
            $message = json_decode($data);

            // If we got a valid receipt, update the fingerprint for this server.
            $fingerprint = $message['fingerprint'];
            if ($fingerprint) {
                $peer->setFingerprint($fingerprint);
            }
        });

    }
    /* */
}