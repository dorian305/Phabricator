<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class AphlictPeerList {

    /* PROPERTIES */
    public $peers;
    public $fingerprint;
    /* */

    // Constructor
    function __construct(){
        $this->peers = [];
        $this->fingerprint = generateFingerprint();
    }

    /* METHODS */
    // Generating fingerprint
    public function generateFingerprint(){
        return Uuid::uuid4()->toString();
    }

    // Returning server fingerprint
    public function getFingerprint(){
        return $this->fingerprint;
    }

    // Adding new peer to peerlist
    public function addPeer($peer){
        array_push($this->peers, $peer);
        return $this;
    }

    // Adding fingerprint to the messtage
    public function addFingerprint($message){
        $fingerprint = $this.getFingerprint(); // Storing this server's fingerprint

        $touched = $message->touched ? $message->touched : [];
        foreach ($touched as $fp){
            if ($fp == $fingerprint) return;
        }
        array_push($touched, $fingerprint);
        $message->touched = $touched;
        
        return $message;
    }

    // Broadcasting the messtage
    public function broadcastMessage($instance, $message){
        $touches = []; // Array of fp's that will hold fp's that touched the message
        $touched = $message->touched; // Holds fp's that already touched the message
        foreach ($touched as $fp){
            $touches[$fp] = true;
        }

        $peers = $this->peers; // Get all peers
        foreach ($peers as $peer){
            $fingerprint = $peer->getFingerprint(); // Get peer's fp
            // If peer has fp and has already touched the messtage, don't broadcast
            if ($fingerprint and $touches[$fingerprint]) continue

            //Broadcast the messtage
            $peer->broadcastMessage($instance, $message);
        }
    }
}