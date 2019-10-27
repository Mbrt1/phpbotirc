<?php

/**
* Simple PHP IRC Logger
*
* PHP Version 5
*/

//So the bot doesn't stop.
set_time_limit(0);
ini_set('display_errors', 'on');

/* --- Varibles and Config Info --- */

//Sample connection data.
$config = array(
//General Config Info
'server' => 'chat.freenode.net',
'port'   => 6667,
'name'   => 'aysbotlogger',
'nick'   => 'aysbot',
'pass'   => '',

//Logging Config Info
'channel' => '#ays-diskusi',
'logging' => true,
'warning' => true,
);

/*
//Set your connection data.
$config = array(
//General Config Info
'server' => 'irc.example.com',
'port'   => 6667,
'name'   => 'real name',
'nick'   => 'user',
'pass'   => 'pass',

//Logging Config Info
'channel' => '#channel',
'logging' => true,
'warning' => true,
);
*/

/* --- IRCBot Class --- */

class IRCBot {

//This is going to hold our TCP/IP connection
var $socket;

//This is going to hold all of the messages both server and client
var $ex = array();
//var $logging = true;

/*
Construct item, opens the server connection, logs the bot in
@param array
*/

function __construct($config)
{
$this->socket = fsockopen($config['server'], $config['port']);
$this->login($config);
$this->main($config);
}

/*
Logs the bot in on the server
@param array
*/

function login($config)
{
$this->send_data('USER', $config['nick'].' wildphp.com '.$config['nick'].' :'.$config['name']);
$this->send_data('NICK', $config['nick']);
$this->join_channel($config['channel']);

if($config['logging']) {
$date = date("n-j-y");
$time = date('h:i:s A');
$logfile = fopen("$date-log.html","a");
fwrite($logfile,"<br/>**************** Logging Started at $time ****************<br/>");
fclose($logfile);

//Warn that logging has been enabled
if($config['warning']) {
$this->send_data('PRIVMSG '.$config['channel'].' :', "Chat Logging has been [Enabled]");
}
}
}

/*
This is the workhorse function, grabs the data from the server and displays on the browser
*/

function main($config)
{
$data = fgets($this->socket, 256);

echo nl2br($data);

flush();

$this->ex = explode(' ', $data);

if($this->ex[0] == 'PING')
{
$this->send_data('PONG', $this->ex[0]); //Plays ping-pong with the server to stay connected.
}

//Logs the chat
if($config['logging'])
{
$logtxt = $this->filter_log($this->ex[1], $this->ex[2], $this->ex[0], $this->get_msg($this->ex)); //Gets human readable text from irc data
if($logtxt != null) { //Writes to log if it is a message
$date = date("n-j-y");
$logfile = fopen("$date-log.html","a");
fwrite($logfile,"$logtxt<br />");
fclose($logfile);
}
}

$command = str_replace(array(chr(10), chr(13)), '', $this->ex[3]);

switch($command) //List of commands the bot responds to from a user.
{
case ':!join':
$this->join_channel($this->ex[4]);
break;

case ':!quit':
$this->send_data('QUIT', 'Wildphp.com Made Bot');
break;

case ':!op':
$this->op_user();
break;

case ':!deop':
$this->op_user('','', false);
break;

case ':!protect':
$this->protect_user();
break;

case ':!say':
$message = "";
for($i=4; $i <= (count($this->ex)); $i++)
{
$message .= $this->ex[$i]." ";
}

$this->send_data('PRIVMSG '.$config['channel'].' :', $message);
break;

case ':!restart':
//Warn that logging has been disabled
if($config['warning']) {
$this->send_data('PRIVMSG '.$config['channel'].' :', "Chat Logging has been [Disabled]");
}

echo "<meta http-equiv=\"refresh\" content=\"3\">";
if($config['logging']) {
$date = date("n-j-y");
$time = date('h:i:s A');
$logfile = fopen("$date-log.html","a");
fwrite($logfile,"<br/>**************** Logging Ended at $time ****************<br/>");
fclose($logfile);
}
exit;
case ':!shutdown':
//Warn that logging has been disabled
if($config['warning']) {
$this->send_data('PRIVMSG '.$config['channel'].' :', "Chat Logging has been [Disabled]");
}

if($config['logging']) {
$date = date("n-j-y");
$time = date('h:i:s A');
$logfile = fopen("$date-log.html","a");
fwrite($logfile,"<br/>**************** Logging Ended at $time ****************<br/>");
fclose($logfile);
}
exit;
}

$this->main($config);
}

/* --- IRCBot Class's Functions --- */

function filter_log($type, $chan, $nick, $msg)
{
$nick = ltrim($nick, ":");
$nick = substr($nick, 0, strpos($nick, "!"));

$msg = ltrim($msg, ":");

if($type == "PRIVMSG")
{
return date("[H:i]")." &amp;amp;amp;lt;".$nick."&amp;amp;amp;gt; ".$msg;
}
return null    ;
}

function get_msg($arr)
{
$message = "";
for($i=3; $i <= (count($this->ex)); $i++)
{
$message .= $this->ex[$i]." ";
}
return $message;
}

function send_data($cmd, $msg = null) //displays stuff to the broswer and sends data to the server.
{
if($msg == null)
{
fputs($this->socket, $cmd."\r\n");
echo '<strong>'.$cmd.'</strong><br />';
} else {

fputs($this->socket, $cmd.' '.$msg."\r\n");
echo '<strong>'.$cmd.' '.$msg.'</strong><br />';
}

}

function join_channel($channel) //Joins a channel, used in the join function.
{
if(is_array($channel))
{
foreach($channel as $chan)
{
$this->send_data('JOIN', $chan);
}

} else {
$this->send_data('JOIN', $channel);
}
}

function protect_user($user = '')
{
if($user == '')
{
if(php_version() >= '5.3.0')
{
$user = strstr($this->ex[0], '!', true);
} else {
$length = strstr($this->ex[0], '!');
$user   = substr($this->ex[0], 0, $length);
}
}

$this->send_data('MODE', $this->ex[2] . ' +a ' . $user);
}

function op_user($channel = '', $user = '', $op = true) {
if($channel == '' || $user == '')
{
if($channel == '')
{
$channel = $this->ex[2];
}

if($user == '')
{

if(php_version() >= '5.3.0')
{
$user = strstr($this->ex[0], '!', true);
} else {
$length = strstr($this->ex[0], '!');
$user   = substr($this->ex[0], 0, $length);
}
}
}

if($op)
{
$this->send_data('MODE', $channel . ' +o ' . $user);
} else {
$this->send_data('MODE', $channel . ' -o ' . $user);
}
}
}

//Start the bot
$bot = new IRCBot($config);
?>
