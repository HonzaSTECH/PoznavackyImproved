<?php
session_start();

$userdata = $_SESSION['user'];
$username = $userdata['name'];

//Odhlašování
unset($_SESSION['user']);

//Odstraňování instantLogin cookie
$cookie_code = $_COOKIE['instantLogin'];
setcookie('instantLogin','',0,'/');
$_COOKIE['instantLogin'] = NULL;

//Odstraňování kódu z databáze
include 'included/connect.php';
$query = "DELETE FROM sezeni WHERE kod_cookie='".md5($cookie_code)."'";
mysqli_query($connection, $query);

include 'included/logger.php';
fileLog("Uživatel $username se odhlásil", "../log.txt");

header("Location: ../index.php");
