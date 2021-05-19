<?php
// Include the AWS SDK for PHP
require 'aws-sdk-for-php/aws-autoloader.php';
use Aws\S3\S3Client;
require "predis/autoload.php";
Predis\Autoloader::register();

$instance_id = file_get_contents("http://instance-data/latest/meta-data/instance-id");

// Database connection parameters
$db_hostname = "localhost";
$db_database = "web_demo";
$db_username = "username";
$db_password = "password";
$db = open_db_connection($db_hostname, $db_database, $db_username, $db_password);

// Image upload options
$storage_option = "hd";	// hd or s3
$hd_folder  = "uploads";
$s3_region  = "us-east-2";
$s3_bucket  = "your_s3_bucket_name";
$s3_prefix  = "uploads";
$s3_baseurl = "https://bucket-name.s3-region-name.amazonaws.com/";
$enable_cf  = false;
$cf_baseurl = "http://xxxxxxxxxxxxxx.cloudfront.net/";
if ($storage_option == "s3")
{
	$s3_client = S3Client::factory(array('region' => $s3_region, 'version' => 'latest'));
}

// Simulate latency, in seconds
$latency = 0;

// Cache configuration
$enable_cache = false;
$cache_type = "memcached";	// memcached or redis
$cache_key  = "images_html";
if ($enable_cache && ($cache_type == "memcached"))
{
	$cache = open_memcache_connection();
}
else if ($enable_cache && ($cache_type == "redis"))
{
	$cache = open_redis_connection();
}

function open_db_connection($hostname, $database, $username, $password)
{
	// Open a connection to the database
	$db = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8", $username, $password);
	return $db;
}

function open_memcache_connection()
{	
	// Open a connection to the memcache server
	$mem = new Memcached();
	$mem->addServer('web-demo.xxxxxx.0001.use2.cache.amazonaws.com', 11211);	// node 1
	$mem->addServer('web-demo.xxxxxx.0002.use2.cache.amazonaws.com', 11211);	// node 2
	$mem->addServer('web-demo.xxxxxx.0003.use2.cache.amazonaws.com', 11211);	// node 3
	return $mem;
}

function open_redis_connection()
{
	$parameters = [
    'tcp://web-demo.xxxxxx.clustercfg.use2.cache.amazonaws.com:6379'    // configuration endpoint
	];
	$options = [
    	'cluster' => 'redis'
	];

	$redis = new Predis\Client($parameters, $options);
	return $redis;
}
?>
