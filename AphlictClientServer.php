<?php

require_once __DIR__ . '/vendor/autoload.php';

class AphlictClientServer {
    public $server;
    public $lists;
    public $adminServers;

    // Constructor
    function __construct($server){
        $this->server = $server;
        $this->lists = [];
        $this->adminServers = [];

        // Server on request
        $this->server->on('Request', function(Swoole\Http\Request $request, Swoole\Http\Response $response)){
            $response->status(501, 'Method not allowed');
            $response->end("HTTP/501 Use Websockets\n");
        }
    }

    // Get listeners list
    public function getListenerList($instance){
        if ($this->lists[$instance]){
            $this->lists[$instance] = new AphlictListenerList($instance);
        }
        return $this->lists[$instance];
    }

    // Get history
    public function getHistory($age){
        $results = [];
        $servers = $this->adminServers;
        foreach ($servers as $adminServer){
            $messages = $adminServer->getHistory($age);
            foreach ($messages as $message){
                array_push($results, $message);
            }
        }
        return $result;
    }

    // Parse instance from path
    public function parseInstanceFromPath($path){
        // Path must contain ~, if it doesn't, it's not an instance name.
        if (!strpos($path, "~")){
            return "default";
        }
        $instance = explode($path, "~")[1];
        // Trim '/'
        $instance = trim($instance, "/");
        if (count($instance) <= 0){
            return "default";
        }
        return $instance;
    }

    // Starting websocket server
    public function listen(){
        // Ovdje treba iscupat iz $this->server IP i vrata na koja slusa i stavit unutar ispod linije
        $server = new Server("0.0.0.0", 9502);

        // Implement check for connection upgrade?

        // $server->on("Start", function(Server $server){
        //     echo "Swoole WebSocket Server is started at http://127.0.0.1:9502\n";
        // });

        // On new connection
        $listener;
        $server->on('Open', function(Server $server, Swoole\Http\Request $request) use ($listener){
            $path = parse_url($request->server['request_uri']);
            $instance = $this->parseInstanceFromPath($path);
            $listener = $this->getListenerList($instance); // need to apply .addListener according to nodejs file, method nowhere to be found
            // Log and tracing need to add
        });

        // New data received
        $server->on('Message', function(Server $server, Frame $frame) use ($listener){
            $message;
            try {
                $message = json_decode($frame);
            } catch (Exception $error){
                // Output to CLI "Invalid message" . $error;]
                return;
            }

            $message = strtolower($message);
            switch($message){
                case "subscribe": {
                    // output to CLI: Subscribed
                    json_encode($message["data"]);
                    $listener->subscribe($message["data"]); // Subscribe method not found anywhere
                    break;
                }
                case "unsubscribe": {
                    // output to CLI: Unsubscribed
                    json_encode($message["data"]);
                    $listener->unsubscribe($message["data"]); // Unsubscribe method not found anywhere
                    break;
                }
                case "replay": {
                    $age = $message["data"]["age"] || 60000;
                    $min_age = microtime(true) - $age;
                    $old_messages = $this->getHistory($min_age);
                    foreach ($old_messages as $old_message){
                        if (!$listener->isSubscribedToAny($old_message["subscribers"])) continue;
                        try {
                            $listener->writeMessage($old_message);
                        } catch (Exception $error){
                            break;
                        }
                    }
                    break;
                }
                case "ping": {
                    $pong = [
                        "type" -> "pong",
                    ]
                    try {
                        $listener->writeMessage($pong);
                    } catch (Exception $error){
                        // Ignore any issues here who cares
                    }
                    break;
                }

                default: {
                    // output to CLI: "Unrecognized command . $message["command"]
                }
            }
        });

        // Connection closes
        $server->on('Close', function(Server $server, int $fd){
            $this->getListenerList($instance)->removeListener($listener);
            // trace("disconnected");
        });
    }
}