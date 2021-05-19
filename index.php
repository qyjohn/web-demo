<?php
/*
 *
 * The first part handles application logic.
 *
 */

include("config.php");
session_start();
$server   = $_SERVER['SERVER_ADDR'];

// Simulate latency 
sleep($latency);

if (isset($_POST['username'])) 
{
	// This is a login request
	process_login($_POST['username']);
}

if (isset($_GET['logout']))
{
	// This is a logout request
	process_logout();
}

if (isset($_FILES["fileToUpload"]) && isset($_SESSION['username']))
{
	// Check file type before processing
	$file_temp = "/tmp/".basename($_FILES["fileToUpload"]["name"]);
	$file_size = $_FILES["fileToUpload"]["size"];
	error_log("Handling $file_temp with $file_size bytes");

	$file_type = strtolower(pathinfo($file_temp, PATHINFO_EXTENSION));
	if(($file_type != "jpg") && ($file_type != "png") && ($file_type != "jpeg") && ($file_type != "gif") ) 
	{
		// Not an image file, ignore the upload request
	}
	else
	{
		$username = $_SESSION['username'];
		// This is an image upload request, save the file first
		if ($storage_option == "hd")
		{
			// In config.php, we specify the storage option as "hd"
			$key = save_upload_to_hd($_FILES["fileToUpload"], $hd_folder);
			add_upload_info($db, $username, $key);
		}
		else if ($storage_option == "s3")
		{
			// In config.php, we specify the storage option as "S3"
			$key = save_upload_to_s3($s3_client, $_FILES["fileToUpload"], $s3_bucket, $s3_prefix);
			add_upload_info($db, $username, $key);
		}

		if ($enable_cache)
		{
			// Delete the cached record, the user will query the database to get an updated version
			if ($cache_type == "memcached")
			{
				$cache->delete($cache_key);
			}
			else if ($cache_type == "redis")
			{
				$cache->del($cache_key);
			}
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
	// Rename the target file with a UUID
	$ext = pathinfo($uploadedFile["name"], PATHINFO_EXTENSION);
	$uuid = uniqid();
	$key = $uuid.".".$ext;

	// Copy the upload file to the target file
	$tgtFile  = $folder."/".$key;	
	move_uploaded_file($uploadedFile["tmp_name"], $tgtFile);
	return $key;
}

function save_upload_to_s3($s3_client, $uploadedFile, $s3_bucket, $s3_prefix)
{
	try 
	{
		// Rename the target file with a UUID
		$ext = pathinfo($uploadedFile["name"], PATHINFO_EXTENSION);
		$uuid = uniqid();
		if (empty($s3_prefix))
		{
			$key = $uuid.".".$ext;		
		}
		else
		{
			$key = $s3_prefix."/".$uuid.".".$ext;		
		}

		// Upload the uploaded file to S3 bucket
		$s3_client->putObject(array(
			'Bucket' => $s3_bucket,
			'Key'    => $key,
			'SourceFile' => $uploadedFile["tmp_name"],
			'ACL'    => 'public-read'
	    ));
		error_log("Uploaded to S3 as s3://$s3_bucket/$key.");
	} catch (S3Exception $e) 
	{
		echo "There was an error uploading the file to S3.\n";
		return false;
	}	

	return $key;
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
        error_log("Getting latest $count records from database.");

	// Geting the latest records from the upload_images table
	$sql = "SELECT * FROM upload_images ORDER BY timeline DESC LIMIT $count";
	$statement = $db->prepare($sql);
	$statement->execute();
	$images = $statement->fetchAll(PDO::FETCH_ASSOC);
	return $images;
}

function db_rows_2_html($images, $storage_option, $hd_folder, $s3_bucket, $s3_baseurl, $enable_cf, $cf_baseurl)
{
	$html = "\n";
	if ($enable_cf == true)
	{
	        // Images are on hard disk
        	foreach ($images as $image)
        	{
                	$filename = $image["filename"];
                	$url = $cf_baseurl.$filename;
               	 	$html = $html."<img src='$url' width=200px height=150px>\n";
        	}
	}	
	else if ($storage_option == "hd")
	{
	        // Images are on hard disk
        	foreach ($images as $image)
        	{
                	$filename = $image["filename"];
                	$url = $hd_folder."/".$filename;
               	 	$html = $html."<img src='$url' width=200px height=150px>\n";
        	}
	}
	else if ($storage_option == "s3")
	{
        	// Images are on S3
        	foreach ($images as $image)
        	{
                	$filename = $image["filename"];
	                $url = $s3_baseurl.$filename;	                	
                	$html = $html. "<img src='$url' width=200px height=150px>\n";
        	}
	}
	return $html;	
}
?>

<?php
/*
 *
 * The second part handles user interface.
 *
 */
echo "<html>\n";
echo "<head>\n";
echo "<META http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
echo "<title>Scalable Web Application</title>\n";
echo "<script src='demo.js'></script>\n";
echo "</head>\n";
echo "<body>\n";

if (isset($_SESSION['username']))
{
	$username = $_SESSION['username'];
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
	echo "<table width=100% border=0>\n";
	echo "<tr>\n";
		echo "<td><H1>$server</H1></td>\n";
		echo "<td align='right'>\n";
			echo "<form action='index.php' method='post'>";
			echo "Enter Your Name: <br>";
			echo "<input type='text' id='username' name ='username' size=20><br>";
			echo "<input type='submit' value='login'/>";
			echo "</form>\n";
		echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "<HR>\n";
}

// Get the most recent N images
if ($enable_cache)
{
	error_log("Cache enabled, try to obtain the cached version.");
	// Attemp to get the cached records for the front page
	$images_html = $cache->get($cache_key);
	if (!$images_html)
	{
		// If there is no such cached record, get it from the database
		$images = retrieve_recent_uploads($db, 10, $storage_option);
		// Convert the records into HTML
		$images_html = db_rows_2_html($images, $storage_option, $hd_folder, $s3_bucket, $s3_baseurl, $enable_cf, $cf_baseurl);
		// Then put the HTML into cache
		$cache->set($cache_key, $images_html);
	}
}
else
{
	// This statement get the last 10 records from the database
	$images = retrieve_recent_uploads($db, 10, $storage_option);
	$images_html = db_rows_2_html($images, $storage_option, $hd_folder, $s3_bucket, $s3_baseurl, $enable_cf, $cf_baseurl);
}
// Display the images
echo $images_html;

$session_id = session_id();
echo "<hr>";
echo "Session ID: ".$session_id;
echo "\n</body>";
echo "\n</html>";
?>
