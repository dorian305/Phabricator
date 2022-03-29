<?php

require_once __DIR__ . '/vendor/autoload.php';

class AphlictListener {

    /* PROPERTIES */
    public $id;
    public $socket;
    public $path;
    public $subscriptions;
    /* */

    // Constructor
    function __construct($id, $socket, $path){
        $this->id = $id;
        $this->socket = $socket;
        $this->path = $path;
        $this->subscriptions = [];
    }

    /* METHODS */
    // Return id
    public function getID(){
        return $this->id;
    }

    // Subscribe to something idk
    public function subscribe($phids){
        foreach ($phids as $phid){
            $this->subscriptions[$phid] = true;
        }

        return $this;
    }

    // Unsubscribe from something idk
    public function unsubscribe($phids){
        foreach ($phids as $phid){
            unset($this->subscriptions[$phid]); // Remove elem from array
        }

        return $this;
    }

    // Check if any subscribers
    public function isSubscribedToAny($phids){
        $intersection = array_filter($phids, $phid => in_array($phid, $this->subscriptions));
        return array_filter($phids, $phid => in_array($phid, $this->subscriptions)) > 0;
    }

    // Get socket
    public function getSocket(){
        return $this->socket;
    }

    // Get description
    public function getDescription(){
        return "Listener/{$this->getID()}/{$this->path}";
    }

    // Write messtage (Need to check how to send to peers with swoole)
    public function writeMessage($message){
        //$this->socket.send(json_encode($message));
    }
}