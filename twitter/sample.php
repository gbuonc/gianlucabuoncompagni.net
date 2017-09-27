<?php
// fill in your application consumer key & consumer secret & your Twitter username
$consumer_key= 'RYMpGmlufCqxCAcDmTsmZg';
$consumer_secret = 'B7FlvUf67MaWbAU6qg32bo5djCSKfYCCt7ghQphE4k';
$screen_name = 'studiopym';

$cache_time = 10 * 60; // 10 minutes for caching
$cache_file = basename( $_SERVER['PHP_SELF']).'.json';

// Bearer Token returned from first request
$bearer_token = '';

/* -- define JSONP callback functions -- */
function callback_start(){
	// JSONP callback parameter ?
	$callback = isset($_GET['callback']) ? $_GET['callback'] : '';
 
	// Content-type for JSON or JSONP
	if( empty( $callback ) ){
		header('Content-Type: application/json; charset=utf-8');
	}else{
		header('Content-Type: application/javascript; charset=utf-8');
		// callback and start Parentheses 
		print $callback.'(';
	};
}

function callback_end(){
	// JSONP callback parameter ?
	$callback = isset($_GET['callback']) ? $_GET['callback'] : '';
 	
 	// callback and start Parentheses 
	if( !empty( $callback ) ) print ')';
}

/* -- define caching Functions -- */
function cache_start() {
	global $cache_file, $cache_time;
	
	// Serve from the cache if it is less than $cache_time
	if( file_exists($cache_file) && ( time() - $cache_time < filemtime($cache_file) ) ) {
		header('x-cached: '.date('Y-m-d H:i:s', filemtime($cache_file)));
			
		include($cache_file);
		
		callback_end(); 		
		
		exit;
	}

	ob_start(); // start the output buffer
}	

function cache_end(){
	global $cache_file;
	
	// open the cache file for writing
	$fp = fopen($cache_file, 'wb'); 
	// save the contents of output buffer to the file
	fwrite($fp, ob_get_contents());
	// close the file
	fclose($fp); 
	// Send the output to the browser
	ob_end_flush(); 
}


/* -- Define Twitter API functions -- */
// Handle HTTP POST/GET requests  
function requestString( $op ){
	$headers = array();
	
	// custom headers?
	if( is_array($op['headers'])){
		foreach ( $op['headers'] as &$value)  $headers[ count($headers) ] =  $value;
	};
	
	$ch = curl_init();  
	
	 // POST?
	if( !empty($op['post']) ){
		$headers[ count($headers) ] = 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8';
		$headers[ count($headers) ] = 'Content-Length: '.(strlen( $op['post'] ));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $op['post']); // POST vars
	};
	
	curl_setopt($ch, CURLOPT_URL, $op['url']);  // request URL
	curl_setopt($ch, CURLOPT_HTTPHEADER, $op['headers']); // custom headers
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($ch, CURLOPT_HEADER, 0); 
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); // Accept Gzip to speedup the request
	
	// if you are testing this script locally on windows server, you might need to set CURLOPT_SSL_VERIFYPEER to 'false' to skip errors with SSL verification
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // verifying the peer's certificate
	
	$result = curl_exec ($ch); 

	curl_close($ch); 
 
	return $result;
}


//	Get the Bearer Token https://dev.twitter.com/docs/auth/application-only-auth
function get_bearer_token(){
	global $bearer_token, $consumer_key, $consumer_secret;

	$token_cred = urlencode($consumer_key).':'.urlencode($consumer_secret);
	$base64_token_cred = base64_encode($token_cred);
	
	$result = requestString( array(
						'url' => 'https://api.twitter.com/oauth2/token'
						,'post' => 'grant_type=client_credentials'
						,'headers' => array('Authorization: Basic '. $base64_token_cred)
						) );
	$json = json_decode($result);
	
	$bearer_token =  $json->{'access_token'};
}

 
// Invalidates the Bearer Token if it is compromised or to invalidate it
function invalidate_bearer_token(){
	global $bearer_token, $consumer_key, $consumer_secret;
	
	$token_cred = urlencode($consumer_key).':'.urlencode($consumer_secret);
	$base64_token_cred = base64_encode($token_cred);
	
	requestString( array(
					'url' => 'https://api.twitter.com/oauth2/invalidate_token'
					,'post' => 'access_token='.$bearer_token
					,'headers' => array('Authorization: Basic '. $base64_token_cred)
					) );
}

// Get User info https://dev.twitter.com/docs/api/1.1/get/users/show
function users_show(){
	global $bearer_token, $screen_name;
	
	return requestString( array(
						'url' => 'https://api.twitter.com/1.1/users/show.json?screen_name='. urlencode($screen_name)
						,'headers' => array( 'Authorization: Bearer '.$bearer_token )
						) );
}

// Get User timeline https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
function user_timeline(){
	global $bearer_token, $screen_name;
	
	return requestString( array(
						'url' => 'https://api.twitter.com/1.1/statuses/user_timeline.json?count=5&trim_user=1&screen_name='. urlencode($screen_name)
						,'headers' => array( 'Authorization: Bearer '.$bearer_token )
						) );
}

// Now Call functions
callback_start();
//cache_start();

get_bearer_token(); // get the bearer token
print '[';

print users_show(); //  get user data
print ',';
print user_timeline(); // Get user timeline

print ']';


//cache_end();
callback_end();
?>
