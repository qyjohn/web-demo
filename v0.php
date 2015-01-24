<?php
/*
 *
 * The first part handles application logic.
 *
 */


session_start();

if (isset($_POST['username'])) 
{
	// This is a login request
	$username = $_POST['username'];
	process_login($username);
}

if (isset($_GET['logout']))
{
	// This is a logout request
	process_logout();
}


function process_login($username)
{
	$_SESSION['username'] = $username;
}

function process_logout()
{
	session_unset();
	session_destroy();
}


$server = $_SERVER['REMOTE_ADDR'];
?>

<?php
/*
 *
 * The second part handles user interface.
 *
 */

if (isset($_SESSION['username']))
{
	echo "<H1>$server</H1>";
}
else
{
	echo "<H1>$server</H1>";
}

?>
