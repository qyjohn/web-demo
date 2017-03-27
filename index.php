<?php
/*
 *
 * The first part handles application logic.
 *
 */

include("config.php");
session_start();
$server   = $_SERVER['SERVER_ADDR'];
$username = $_SESSION['username'];
$db = open_db_connection($db_hostname, $db_database, $db_username, $db_password);

// Simulate latency 
sleep($latency);

if (isset($_POST['username'])) 
{
	// This is a login request
	process_login($_POST['username']);
	// Get the new username
	$username = $_SESSION['username'];
}

if (isset($_GET['logout']))
{
	// This is a logout request
	process_logout();
}

if (isset($_FILES["fileToUpload"]))
{
	// Check file type before processing
	$file_temp = "/tmp/".basename($_FILES["fileToUpload"]["name"]);
	$file_type = strtolower(pathinfo($file_temp, PATHINFO_EXTENSION));
	if(($file_type != "jpg") && ($file_type != "png") && ($file_type != "jpeg") && ($file_type != "gif") ) 
	{
		// Not an image file, ignore the upload request
	}
	else
	{
		// This is an image upload request, save the file first
		if ($storage_option == "hd")
		{
			// In config.php, we specify the storage option as "hd"
			save_upload_to_hd($_FILES["fileToUpload"], $hd_folder);
		}
		else if ($storage_option == "s3")
		{
			// In config.php, we specify the storage option as "S3"
			save_upload_to_s3($s3_client, $_FILES["fileToUpload"], $s3_bucket);
		}

		// Then write a record to the database
		$filename = $_FILES["fileToUpload"]["name"];
		add_upload_info($db, $username, $filename);

		if ($enable_cache)
		{
			// Delete the cached record, the user will query the database to 
			// get an updated version
			$mem = open_memcache_connection($cache_server);
			$mem->delete("front_page");
		}
	}
}


function process_login($username)
{
	// Simply write username to session data
	$_SESSION['username'] = $username;
}

function process_logout()
{
	// Unset all of the session variables.
	$_SESSION = array();

	// If it's desired to kill the session, also delete the session cookie.
	// Note: This will destroy the session, and not just the session data!
	if (ini_get("session.use_cookies")) 
	{
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
	}

	// Finally, destroy the session.
	session_destroy();
}

function save_upload_to_hd($uploadedFile, $folder)
{
	// Copy the uploaded file to "uploads" folder
	$filename = $uploadedFile["name"];
	$tgtFile  = $folder."/".$filename;	
	move_uploaded_file($uploadedFile["tmp_name"], $tgtFile);
}

function save_upload_to_s3($s3_client, $uploadedFile, $s3_bucket)
{
	try 
	{
		// Upload the uploaded file to S3 bucket
		$key = $uploadedFile["name"];
		$s3_client->putObject(array(
			'Bucket' => $s3_bucket,
			'Key'    => $key,
			'SourceFile' => $uploadedFile["tmp_name"],
			'ACL'    => 'public-read'
	    ));
	} catch (S3Exception $e) 
	{
		echo "There was an error uploading the file.\n";
		return false;
	}	
}


function open_db_connection($hostname, $database, $username, $password)
{
	// Open a connection to the database
	$db = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8", $username, $password);
	return $db;
}

function add_upload_info($db, $username, $filename)
{
	// Add a new record to the upload_images table
	$sql = "INSERT INTO upload_images (username, filename) VALUES (?, ?)";	
	$statement = $db->prepare($sql);
	$statement->execute(array($username, $filename));
}

function retrieve_recent_uploads($db, $count)
{
	// Print a message so that the user knows these records come from the DB.
	echo "Getting latest $count records from database.<br>";

	// Geting the latest records from the upload_images table
	$sql = "SELECT * FROM upload_images ORDER BY timeline DESC LIMIT $count";
	$statement = $db->prepare($sql);
	$statement->execute();
	$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
	return $rows;
}

function open_memcache_connection($hostname)
{	
	// Open a connection to the memcache server
	$mem = new Memcached();
	$mem->addServer($hostname, 11211);
	return $mem;
}



?>

<?php
/*
 *
 * The second part handles user interface.
 *
 */
echo "<html>";
echo "<head>";
echo "<META http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
echo "<title>Scalable Web Application</title>";
echo "<script src='demo.js'></script>";
echo "</head>";
echo "<body>";

if (isset($_SESSION['username']))
{
	// This section is shown when user is login
	echo "<table width=100% border=0>";
	echo "<tr>";
		echo "<td><H1>$server</H1></td>";
		echo "<td align='right'>";
			echo "$username<br>";
			echo "<a href='index.php?logout=yes'>Logout</a>";
		echo "</td>";
	echo "</tr>";
	echo "</table>";
	echo "<HR>";

	echo "In this demo, we assume that you are uploading images files with file extensions such as JPG, JPEG, GIF, PNG.<br>&nbsp;<br>";

	echo "<form action='index.php' method='post' enctype='multipart/form-data'>";
	echo "<input type='file' id='fileToUpload' name='fileToUpload' id='fileToUpload' onchange='check_file_type();'>";
	echo "<input type='submit' value='Upload Image' id='submit_button' name='submit_button' disabled>";
	echo "</form>";

}
else
{
	// This section is shown when user is not login
	echo "<table width=100% border=0>";
	echo "<tr>";
		echo "<td><H1>$server</H1></td>";
		echo "<td align='right'>";
			echo "<form action='index.php' method='post'>";
			echo "Enter Your Name: <br>";
			echo "<input type='text' id='username' name ='username' size=20><br>";
			echo "<input type='submit' value='login'/>";
			echo "</form>";
		echo "</td>";
	echo "</tr>";
	echo "</table>";
	echo "<HR>";
}

// Get the most recent N images
if ($enable_cache)
{
	// Attemp to get the cached records for the front page
	$mem = open_memcache_connection($cache_server);
	$images = $mem->get("front_page");
	if (!$images)
	{
		// If there is no such cached record, get it from the database
		$images = retrieve_recent_uploads($db, 10);
		// Then put the record into cache
		$mem->set("front_page", $images, time()+86400);
	}
}
else
{
	// This statement get the last 10 records from the database
	$images = retrieve_recent_uploads($db, 10);
}

// Display the images
echo "<br>&nbsp;<br>";
if ($storage_option == "hd")
{
	// Images are on hard disk
	foreach ($images as $image)
	{
		$filename = $image["filename"];
		$url = "uploads/".$filename;
		echo "<img src='$url' width=200px height=150px>&nbsp;&nbsp;";
	}
}
else if ($storage_option == "s3")
{
	// Images are on S3
	foreach ($images as $image)
	{
		$filename = $image["filename"];
		$url = $s3_baseurl.$s3_bucket."/".$filename;
		echo "<img src='$url' width=200px height=150px>&nbsp;&nbsp;";
	}
}
$session_id = session_id();
echo "<hr>";
echo $session_id;
echo "</body>";
echo "</html>";
?>
