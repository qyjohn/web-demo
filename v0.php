<?php
/*
 *
 * The first part handles application logic.
 *
 */


session_start();
$server   = $_SERVER['REMOTE_ADDR'];
$username = $_SESSION['username'];
$db = open_db_connection();


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
	// This is a upload request, save the file first
	process_upload($_FILES["fileToUpload"]);
	// Then write a record to the database
	$filename = $_FILES["fileToUpload"]["name"];
	add_upload_info($db, $username, $filename);
}


function process_login($username)
{
	// Simply write username to session data
	$_SESSION['username'] = $username;
}

function process_logout()
{
	// Simply destroy session data
	session_unset();
	session_destroy();
}

function process_upload($uploadedFile)
{
	// Copy the uploaded file to "uploads" folder
	$filename = $uploadedFile["name"];
	$tgtFile  = "uploads/".$filename;	
	move_uploaded_file($uploadedFile["tmp_name"], $tgtFile);
}

function open_db_connection()
{
	// Open a connection to the database
	$hostname = 'localhost';
	$database = 'web_demo';
	$username = 'username';
	$password = 'password';
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
	// Geting the latest records from the upload_images table
	$sql = "SELECT * FROM upload_images ORDER BY timeline LIMIT $count";
	$statement = $db->prepare($sql);
	$statement->execute();
	$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
	return $rows;
}

// This statement get the last 10 records from the database
$images = retrieve_recent_uploads($db, 10);

?>

<?php
/*
 *
 * The second part handles user interface.
 *
 */

if (isset($_SESSION['username']))
{
	// This section is shown when user is login
	echo "<table width=100% border=0>";
	echo "<tr>";
		echo "<td><H1>$server</H1></td>";
		echo "<td align='right'>";
			echo "$username<br>";
			echo "<a href='v0.php?logout=yes'>Logout</a>";
		echo "</td>";
	echo "</tr>";
	echo "</table>";
	echo "<HR>";

	echo "In this crappy demo, we assume that you are uploading images files with file extensions such as JPG, JPEG, GIF, PNG.<br>&nbsp;<br>";

	echo "<form action='v0.php' method='post' enctype='multipart/form-data'>";
	echo "<input type='file' name='fileToUpload' id='fileToUpload'>";
	echo "<input type='submit' value='Upload Image' name='submit'>";
	echo "</form>";

}
else
{
	// This section is shown when user is not login
	echo "<table width=100% border=0>";
	echo "<tr>";
		echo "<td><H1>$server</H1></td>";
		echo "<td align='right'>";
			echo "<form action='v0.php' method='post'>";
			echo "Enter Your Name: <br>";
			echo "<input type='text' id='username' name ='username' size=20><br>";
			echo "<input type='submit' value='login'/>";
			echo "</form>";
		echo "</td>";
	echo "</tr>";
	echo "</table>";
	echo "<HR>";
}

// Display the images
echo "<br>&nbsp;<br>";
foreach ($images as $image)
{
	$filename = $image["filename"];
	$url = "uploads/".$filename;
	echo "<img src='$url' width=200px height=150px>&nbsp;&nbsp;";
}

?>
