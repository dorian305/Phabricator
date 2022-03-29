<?php

require_once __DIR__ . '/vendor/autoload.php';

class AphlictAdminServer {
    public $clientServers;
    public $logged;
    public $peerList;
    public $messagesIn;
    public $messagesOut;
    public $server;
    public $startTime;
    public $messageHistory;

    // Constructor
    function __construct($server){
        $this->startTime = date('Y-m-d H:i:s.u');
        $this->messagesIn = 0;
        $this->messagesOut = 0;
        $this->server = $server;
        $this->clientServers = [];
        $this->messageHistory = [];

        // Server on request
        $this->server->on('Request', function(Swoole\Http\Request $request, Swoole\Http\Response $response)){

            // Getting URI array (https://openswoole.com/docs/modules/swoole-http-request-server)
            $requestURL = parse_url($request->server['request_uri']);
            $instance = $requestURL['query']['instance'] || 'default';

            // Publishing a notification
            // Root path
            if ($requestURL['path'] == '/'){
                // POST Method
                if ($request->getMethod() == 'POST'){

                    // When server received data
                    $body = "";
                    $this->server->on('Message', function(Swoole\WebSocket\Server $server, $frame) use ($body){
                        $body = $body + $frame->data;
                    }

                    // When connection is closed
                    $server->on('Close', function(Swoole\WebSocket\Server $server, $fd) use ($body){
                        try {
                            $message = json_encode($body);
                            // Some trace shit here from node file

                            try {
                                $this->transmit($instance, $message, $response);
                            } catch (Exception $error) {
                                // Log exception message to server CLI (Internal server error)
                                $response->status(500, 'Internal server Error');
                            }
                        } catch (Exception $error){
                            // Log exception message to server CLI (Bad request)
                            $response->status(400, 'Bad request');
                        } finally {
                            $response->end();
                        }
                    });
                }
                
                // Not POST method
                else {
                    $response->status(405, 'Method not allowed');
                    $response->end();
                }
            }

            // Path == /status/
            else if ($requestURL['path'] == "/status/"){
                $this->handleStatusRequest($request, $response, $instance);
            }

            // Path not found
            else {
                $response->status(404, 'Not found');
                $response->end();
            }
        }
    }

    // Getting listeners list
    public function getListenerLists($instance){
        $clients = $this->clientServers;
        $lists = [];
        foreach ($clients as $client){
            array_push($lists, $client.getListenerList($instance));
        }
        return $lists;
    }

    // Handle status request
    public function handleStatusRequest($request, $response, $instance){
        $active_count = 0;
        $total_count = 0;
        $lists = $this->getListenerLists($instance);
        foreach ($lists as $list){
            $active_count = $active_count + $list->getActiveListenerCount();
            $total_count = $total_count + $list->getTotalListenerCount();
        }
        $now = microtime(true);
        $history_size = count($this->messageHistory);
        $history_age = null;
        if (history_size){
            $history_age = ($now - $this->messageHistory[0]['timestamp']);
        }

        $server_status = [
            'instance' => $instance,
            'uptime' => ($now - $this->startTime),
            'clients_active' => $active_count,
            'clients_total' => $total_count,
            'messages_in' => $this->messagesIn,
            'messages_out' => $this->messagesOut,
            'version' => 7,
            'history_size' => $history_size,
            'history_age' => $history_age,
        ];

        $response->header('Content-Type', 'application/json');
        $response->status(200);
        $response->write(json_encode($server_status);
        $response->end();
    }

    // Transmit
    public function transmit($instance, $message, $response){
        $now = microtime(true);
        array_push($this->messageHistory, [
            "timestamp" => $now,
            "message" => $message
        ]);
        $this->purgeHistory();
        $peer_list = $this->peerList;
        $message = $peer_list->addFingerprint($message);
        if ($message){
            $lists = $this->getListenerLists($instance);
            foreach ($lists as $list){
                $listeners = $list.getListeners();
                $this->transmitToListeners($list, $listeners, $message);
            }
            $peer_list->broadcastMessage($instance, $message);
        }
        $receipt = [
            "fingerprint" => $this->peerList->getFingerprint();
        ];
        $response->header('Content-Type', 'application/json');
        $response->status(200);
        $response->write(json_encode($receipt);
        $response->end();
    }

    // Transmitting to all connected
    public function transmitToListeners($list, $listeners, $message){
        foreach ($listeners as $listener){
            if (!$listener->isSubscribedToAny($message->subscribers)) continue; // Listener is not subscribed, move on to next

            try {
                $listener->writeMessage($message);
                $this->messagesOut = $this->messagesOut + 1;
                // Logging to the CLI about the written message
            } catch (Exception $error){
                $list->removeListener($listener);
                // Logging to the CLI about the written message that failed
            }
        }
    }

    // Get history
    public function getHistory($min_age){
        $history = $this->messageHistory;
        $results = [];
        foreach ($history as $hist){
            if ($hist["timestamp"] >= $min_age){
                array_push($results, $hist["message"]);
            }
        }
        return $results;
    }

    public function purgeHistory(){
        $messages = $this->messageHistory;
        $size_limit = 4096; // Max amount of info that will be read
        // Find the index of the first item we're going to keep. If we have too
        // many items, this will be somewhere past the beginning of the list.
        $keep = max(0, count($messages) - $size_limit);
        $age_limit = 60000; // Max number of miliseconds of history to retain
        // Move the index forward until we find an item that is recent enough to retain
        $now = microtime(true);
        $min_age = ($now - $age_limit);
        for ($keep; $keep < count($messages); $keep++){
            if ($messages[$keep]["timestamp"] >= $min_age) break;
        }
        if ($keep) array_splice($this->messageHistory, 0, $keep); //
    }
}