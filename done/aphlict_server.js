'use strict';

var JX = require('./lib/javelin').JX;
var http = require('http');
var https = require('https');
var util = require('util');
var fs = require('fs');


// function parse_command_line_arguments(argv) {

//   //These are default values that are expected to be passed as a second argument when running node process
//   var args = {
//     test: false,
//     debug: false,
//     config: null
//     };

//     for (var ii = 2; ii < argv.length; ii++) {

//       //Here we are saving into arg variable the argument passed when we ran the node.js process
//       //An example of running node.js process with arguments: node process-args.js one two=three four
//       //With this, we have passed in 3 arguments with 2 default arguments: [/usr/local/bin/node, /Users/mjr/work/node/process-args.js, one, two=three, four].
//       //ii stars from 2 because 0 and 1 argument is irrelevant and we are skipping it
//       //On first iteration, arg = 'one', 2nd iteration, arg = 'two=three', 3rd iteration, arg = 'four'
//       var arg = argv[ii];

//       //Searches for lines in the following format: --anycharactersrepeatingatleastoncethatisntequalsign=anycharactersthatrepeat0ormoretimes
//       //From the example, the regex saves into matches only the value 'two=three', as it satisfied the regex pattern
//       //So first iteration won't do anything because 'one' is invalid argument
//       var matches = arg.match(/^--([^=]+)=(.*)$/);

//     //If matches variable didn't find any valid arguments, throw an error in the following format (for a given argument: one two three):
//     //Unknown argument 'one two three'
//     if (!matches) {
//       throw new Error('Unknown argument "' + arg + '"!');
//       }

//     //If the 2nd match in matches array (assuming it matched at least 2 strings from running process command) is a valid property in args
//     //For example: node process-args.js one two=three four, matches = ['two=three'], matches[1] doesn't exist because it only found 1 match
//     //Not good: node process-args.js one 'two=three another=foo' four
//     if (!(matches[1] in args)) {
//       throw new Error('Unknown argument "' + matches[1] + '"!');
//         }

//     //Update args object properties given correct command
//     //A valid command would be in the following form: node process-args.js test=true debug=false config=someConfigFile
//     args[matches[1]] = matches[2];
//   }

//   //Return args object
//   return args;
// }

// /*
// Function which reads contents of a file and saves it into 'data' variable. Uses 'fs' module for working with filesystem.
// The 'data' variable is then parsed using JSON.parse() to convert the contents of 'data' into javascript array or object.
// EXAMPLE: Assume the following content of 'data': '{"name":"John", "age":30, "city":"New York"}'
// JSON.parse(data) converts the string to an usable JAVASCRIPT object: 
// {
//     name: 'John';
//     age: 30;
//     city: 'New York';
// }
// The data can then be accessed using data.key OR data['key'] (data.name OR data['name'])
// PHP Equivalent: https://www.php.net/manual/en/function.file-get-contents.php (file_get_contents -> read content of a file.)
// Javascript JSON.parse(string) in php is json_decode(string)
// */
// function parse_config(args) {
//   var data = fs.readFileSync(args.config); //Synchronously reading configuration file defined in args.config and storing it into data variable
//   return JSON.parse(data); //Parsing the read file and converting it into valid JSON object
// }

// //Importing Aphlict logger
// require('./lib/AphlictLog');

// //Loading debugging console? into debug variable
// var debug = new JX.AphlictLog();

// var args = parse_command_line_arguments(process.argv); //Reading arguments from node process command 'node process-args.js test=true debug=false config=someConfigFile'
// var config = parse_config(args); //Converting configuration file contents to JSON

// //If either of the properties test and debug are set to true inside args object
// if (args.test || args.debug) {

//   //Need to check inside AphlictLog the methods addConsole and setTrace what do they do
//   debug.addConsole(console);
//   debug.setTrace(true);
// }

//Adds event listener on process exit to exit with exit code that is passed as an argument
function set_exit_code(code) {

  //Listen to when the process has been terminated
    process.on('exit', function () {

    //Exit the process with code 'code'
    process.exit(code);
  });
}

//This chunk deals with trying to run script which you do not have permission to run
process.on('uncaughtException', function(err) {
  var context = null;
  if (err.code == 'EACCES') {
    context = util.format(
      'Unable to open file ("%s"). Check that permissions are set ' +
      'correctly.',
      err.path);
  }

  var message = [
    '\n<<< UNCAUGHT EXCEPTION! >>>',
  ];
  if (context) {
    message.push(context);
  }
  message.push(err.stack);

  debug.log(message.join('\n\n'));
  set_exit_code(1);
});

//Tries to include Web Socket module from node, and throws an error if the module is not installed with node
try {

    //Import web socket
    require('ws');

//Catch exception if wb module is not loaded (not found)
} catch (ex) {
  throw new Error(
    'You need to install the Node.js "ws" module for websocket support. ' +
    'See "Notifications User Guide: Setup and Configuration" in the ' +
    'documentation for instructions. ' + ex.toString());
}

//These files are imported ONLY IF web socket module is loaded, as they are completely dependant on web socket module
require('./lib/AphlictAdminServer');
require('./lib/AphlictClientServer');
require('./lib/AphlictPeerList');
require('./lib/AphlictPeer');

//var ii;

// //Let logs variable contain the values of config.logs which vas previously read through file system module, or an empty array is config.logs is empty.
// var logs = config.logs || [];

// //Iterates through all the logs inside log variable
// for (ii = 0; ii < logs.length; ii++) {

//   //Check how exactly addLog method works in AphlictLog
//   debug.addLog(logs[ii].path);
// }

/*Iterate through the servers array defined in config file that has been converted to JSON (config file servers property which I assume is array of server objects, and the length of it)
config = {
    ...,
    ...,
    servers: [
        { serverProperty1: value1, listen: value2, ssl.key: value3 },
        { serverProperty1: value1, listen: value2, ... },
        { serverProperty1: value1, listen: value2, ... },
        ...
    ],
    ...,
    ...,
}
*/
// var servers = [];
// for (ii = 0; ii < config.servers.length; ii++) {

//   //Save current iteration of server object into spec for easier acces
//   var spec = config.servers[ii];

//   //Set the attribute 'listen' of server object to the address it is listening to OR '0.0.0.0' if it is currently undefined (not set)
//   spec.listen = spec.listen || '0.0.0.0';

//   //If the server object contains property 'ssl.key', update its value to I don't understand the right hand assignment.
//   //The reason for using [] to access new property is because the property name contains '.'
//   if (spec['ssl.key']) {
//     spec['ssl.key'] = fs.readFileSync(spec['ssl.key']);
//   }

//   //Analogous actions are here
//   if (spec['ssl.cert']){
//     spec['ssl.cert'] = fs.readFileSync(spec['ssl.cert']);
//     if (spec['ssl.chain']){
//       spec['ssl.cert'] += "\n" + fs.readFileSync(spec['ssl.chain']); //Delimiter betweed END and BEGIN certificated
//     }
//   }

//   //Add the newly read server OBJECT into the serves array
//   /*
//    servers = [
//     { type: value1, listen: value2, port: portValue, ssl.key: value3, ssl.cert: value4, ssl.chain: value5 },
//    ]
//   */
//   servers.push(spec);
// }

// // If we're just doing a configuration test, exit here before starting any
// // servers.
// //If test property in args object is set to true
// if (args.test) {

//     //Using imported AphlictLog, log the message
//     debug.log('Configuration test OK.');

//   //Add event listener 'on process exit' to exist with exit code 0
//   set_exit_code(0);
//   return;
// }

//Log into console the message of starting servers with their appropriate process ID's
debug.log('Starting servers (service PID %d).', process.pid);

//Logging to console paths to the files where the logs will be logged
for (ii = 0; ii < logs.length; ii++) {
  debug.log('Logging to "%s".', logs[ii].path);
}

var aphlict_servers = [];
var aphlict_clients = [];
var aphlict_admins = [];

//Iterating through all the servers that have been added into server array
for (ii = 0; ii < servers.length; ii++) {

  //Saving server object in server variable for easier access
    var server = servers[ii];

  //is_client will be true if the property 'type' in server object is equal to 'client'
  var is_client = (server.type == 'client');

    var http_server;

    //If server object contains property 'ssl.key'
    if (server['ssl.key']) {

        //Create new object https_config = { key: ssl.key, cert: ssl.cert }
        var https_config = {
          key: server['ssl.key'],
          cert: server['ssl.cert'],
        };

        //Instantiate new server instance with previously defined configuration
        //PHP version: https://www.zend.com/blog/creating-basic-php-web-server-swoole (haven't been able to find SSL version)
        //HTTPS: https://www.swoole.co.uk/docs/modules/swoole-http-server-doc#swoole-http-server-configuration
        http_server = https.createServer(https_config);
    }

  //Server doesnt' have ssl.key property, instantiate server without ssl certificate and key
  else {
    http_server = http.createServer();
  }

    var aphlict_server;

  //If current server is a client server (server.type = 'client'), then instantiate new aphlict client server, else instantiate admin server
  if (is_client) {
    aphlict_server = new JX.AphlictClientServer(http_server);
  } else {
    aphlict_server = new JX.AphlictAdminServer(http_server);
  }

  //I guess newly instantiated server has method for binding the logger, so we pass to the server the logger we created above 'debug' which is AphlictLog
    aphlict_server.setLogger(debug);

  //Start and set aphlict server to listen to the server.listen address on server.port port
  aphlict_server.listen(server.port, server.listen);

  //Logs message "Started Admin || Client server (Port, With SSL || No SSL)
  debug.log(
    'Started %s server (Port %d, %s).',
    server.type,
    server.port,
    server['ssl.key'] ? 'With SSL' : 'No SSL'); //Checks if server has property 'ssl.key' and prints 'With SSL' if it has, 'No SSL' if it doesnt' have it

  //Adding to aphlict_servers newly created aphlict server
  aphlict_servers.push(aphlict_server);


  //If newly created server is a client server, add it to the aphlict_clients array of client servers, otherwise add it to the aphlict_admins array of admin servers
  if (is_client) {
    aphlict_clients.push(aphlict_server);
  } else {
    aphlict_admins.push(aphlict_server);
  }
}

// //Needs reviewing peer list class
// var peer_list = new JX.AphlictPeerList();

// //Logging fingerpring from peer_list variable which is new AphlictPeerList instance
// debug.log(
//   'This server has fingerprint "%s".',
//   peer_list.getFingerprint());

//Reading and saving clusters from config's cluster property which again I assume is an array of cluster objects
/*
config = {
    ...,
    ...,
    servers: [
        { serverProperty1: value1, listen: value2, ssl.key: value3 },
        { serverProperty1: value1, listen: value2, ... },
        { serverProperty1: value1, listen: value2, ... },
        ...
    ],
    cluster: [
        { host: hostValue, port: portValue, protocol: protocolValue }, //Each of these objects is peer variable during iteration
        ...,
        ...,
    ]
    ...,
}
*/
var cluster = config.cluster || [];

//Iterating over array of cluster objects
for (ii = 0; ii < cluster.length; ii++) {

  //Saving cluster into peer variable for easier access
  var peer = cluster[ii];

  //Instantiating new aphlict peer and calling its setters to set values that are read from peer object's properties
  var peer_client = new JX.AphlictPeer()
    .setHost(peer.host)
    .setPort(peer.port)
    .setProtocol(peer.protocol);

  //Appending newly created peer client to the list of Aphlict peers list
  peer_list.addPeer(peer_client);
}

//Looping through each aphlict admin servers
for (ii = 0; ii < aphlict_admins.length; ii++) {

  //Setting currently iterated admin server into admin_server variable for easier access
    var admin_server = aphlict_admins[ii];

  //In currently iterated admin server, setting aphlict client servers array value to the previously created client servers and peer list
  admin_server.setClientServers(aphlict_clients);
  admin_server.setPeerList(peer_list);
}

//And for each client aphlict server, set its admin servers
for (ii = 0; ii < aphlict_clients.length; ii++) {
  var client_server = aphlict_clients[ii];
  client_server.setAdminServers(aphlict_admins);
}