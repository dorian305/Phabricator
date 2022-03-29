<?php

require_once __DIR__ . '/vendor/autoload.php';
// require_once __DIR__ . "/AphlictListener.php";
// require_once __DIR__ . "/AphlictPeer.php";
// require_once __DIR__ . "/AphlictPeerList.php";
// require_once __DIR__ . "/AphlictAdminServer.php";
// require_once __DIR__ . "/AphlictClientServer.php";

//Logger
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

//Extracting command line arguments
function parse_cmd_args($argv){
    global $debug;

    //Defining default arguments (CMD arguments should change the values of these, if provided)
    $default_arguments = [
        'test' => false,
        'debug' => false,
        'config' => null,
    ];

    //Removing first element from argv array (script name)
    array_shift($argv);

    //Run this chunk of code only if at least one of the arguments is provided
    if (! empty($argv)) {

        //Iterating through arguments and checking if arguments passed are valid (test, debug or config with their appropriate values from defined $default_arguments), if not, exit php script with error
        foreach ($argv as $arg) {

            // provjeri ima li =, ako ne izadji
            if (strpos($arg, "=") === false) {
                $debug->warning("Malformed argument");
                exit();
            }
            
            $arg_split = explode('=', strtolower($arg)); //Separating argument around '=' into left and right hand side (arg1=value1 => [arg1, value1]) and turning it to lowercase

            if (
                ($arg_split[0] === 'test' and ($arg_split[1] === 'true' or $arg_split[1] === 'false')) or
                ($arg_split[0] === 'debug' and ($arg_split[1] === 'true' or $arg_split[1] === 'false')) or
                ($arg_split[0] === 'config' and is_file($arg_split[1]))
            ) //is_path checks whether the given path leads to a valid file
            {
                //Update $default_arguments with new argument values
                if ($arg_split[0] === 'config') {
                    $default_arguments[$arg_split[0]] = $arg_split[1];
                } else {

                    //Need to manually convert string boolean to real bool type
                    $bool_value = false;
                    if ($arg_split[1] === 'true') $bool_value = true;
                    else if ($arg_split[1] === 'false') $bool_value = false;

                    //Updating the value
                    $default_arguments[$arg_split[0]] = $bool_value;
                }
            }

            //One of the arguments is invalid, exit the code
            else {
                $error_message = $arg_split[0] === 'config' ? "'$arg_split[1]' is not a valid config file!" : "Unknown argument '$arg_split[0]=$arg_split[1]'";
                $debug->warning($error_message);
                exit();
            }
        }
    } else {
        $debug->warning("No arguments provided");
        exit();
    }

    //Returning the argument array
    return $default_arguments;
}

//Instantiating logger
$debug = new Logger('Aphlict Server');
$debug->pushHandler(new StreamHandler(__DIR__ . '/logs/aphlict_server.log', Logger::DEBUG)); //Logs to /logs/aphlict_server.log
$debug->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG)); //Enables logging into the console as well

//Parsing and storing command line arguments
$args = parse_cmd_args($argv);
$config = null;
if (!$args['config']) {
    $debug->warning("the configuration file has not been provided!");
} else {
    //Parameter true converts the contents of a file into associative array
    $config = json_decode(file_get_contents($args['config']), true);
}


//If script was run in either test or debug mode
if ($args['test'] or $args['debug']) {
    //Add some kind of console where the debugging messages will be displayed and message tracing (whatever that meant)
}

//Iterating through servers defined in $config
$servers = [];
foreach ($config['servers'] as $server) {

    //Set server to listen to predefined value in $config or 0.0.0.0
    $server['listen'] = $server['listen'] or '0.0.0.0';

    //If server has ssl.key property, read the file and store it as a value of property ssl.key (property initally contains path to the ssl.key file)
    if ($server['ssl.key']) $server['ssl.key'] = file_get_contents($server['ssl.key']);

    //Same thing here
    if ($server['ssl.cert']) {
        $server['ssl.cert'] = file_get_contents($server['ssl.cert']);
        if ($server['ssl.chain']) $server['ssl.chain'] += "\n" . file_get_contents($server['ssl.chain']);
    }

    //Adding newly configured server
    array_push($servers, $server);
}

//If script was run as a configuration test, exit here
if ($args['test']) {
    $debug->info("Configuration test OK");
    exit();
}

//Logging message of starting servers
$pid = getmypid();
$debug->info("Starting servers (service PID $pid)");

//Iterating through added servers from the config file
$aphlict_servers = [];
$aphlict_clients = [];
$aphlict_admins = [];
foreach ($servers as $server) {
    $http_server = null; //Will be used for creating new http server instances

    //Used to determine whether the currently iterated server is client server or admin server
    $is_client = false;
    if (strtolower($server['type']) === 'client') $is_client = true;

    //If currently iterated server instance has SSL key, then create HTTPS server
    if ($server['ssl.key']) {
        $http_server = new Swoole\Http\Server($server['listen'], $server['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);

        // Setup the location of SSL cert and key files
        $server->set([

            // Setup SSL files
            'ssl_cert_file' => $server['ssl.key'],
            'ssl_key_file' => $server['ssl.cert'],

            // Enable HTTP andHTTP2 protocol
            'open_http2_protocol' => true,
            'open_http_protocol' => true,
        ]);
    }

    //Else create normal HTTP server
    else {
        $http_server = new Swoole\Http\Server($server['listen'], $server['port']);
    }

    //Instantiating aphlict admin/client server
    // $aphlict_server = $is_client ? /*Instantiate new AphlictClientServer*/ : /*Instantiate new AphlictAdminServer*/ ;
    //// OVDJE TREBA SAD REWORKAT CLIENT I ADMIN SERVERE

    //Set logger for instantiated aphlict server

    //Execute callback function when the server starts up and is ready to accept requests/connections
    $http_server->on("Start", function ($http_server) {

        //Logging message about server starting "Started Admin or Client server (Port, With SSL or No SSL)
        global $debug;
        global $server;
        $address = $server['listen'];
        $type = $server['type'];
        $port = $server['port'];
        $ssl = $server['ssl.key'] ? "With SSL" : "No SSL";
        $debug->info("Started $type server (Address: $address, Port $port, $ssl)");
    });

    //New client connection
    $http_server->on('Connect', function ($http_server, $fd, $reactorID) {

        //Log the ID of the new connection
        global $debug;
        $debug->info("New connection established! Connection ID: #$fd");
    });

    //Closed connection with client
    $http_server->on('Close', function ($http_server, $fd) {
        global $debug;
        $debug->info("Connection closed: #{$fd}.\n");
    });

    // The main HTTP server request callback event, entry point for all incoming HTTP requests
    $http_server->on('Request', function ($request, $response) {
        $response->end("<h1>Hello World!</h1>\n");
    });

    //$aphlict_server.listen($server['port'], $server['listen']);
    $http_server->start();

    //Appending new aphlict server to an array of all aphlict servers
    array_push($aphlict_servers, $aphlict_server);

    //If instantiated server is client server, append to array of client servers, otherwise append to array of admin servers
    $is_client ? array_push($aphlict_clients, $aphlict_server) : array_push($aphlict_admins, $aphlict_server);
}

//Instantiate new peer list server and logging server's fingerprint
$peer_list = new AphlictPeerList();
$peer_list_fingerprint = $peer_list.getFingerprint();
$logger->info("This server has fingerprint {$peer_list_fingerprint}");

//Iterating through clusters defined in $config
$cluster = $config['cluster'] ? $config['cluster'] : [];
foreach ($cluster as $peer) {

    //Instantiating new peer client 
    $peer_client = new AphlictPeer($peer['host'], $peer['port'], $peer['protocol']);

    //Append newly created peer client to the list of aphlict peers list
    $peer_list.addPeer($peer_client);
}

//Iterating through admin servers
foreach ($aphlict_admins as $admin_server) {
    //$admin_server.setClientServers($aphlict_clients);
    //$admin_server.setPeerList($peer_list);
}

//Iterating through client servers
foreach ($aphlict_clients as $client_server) {
    //$client_server.setAdminServers($aphlict_admins)
}
