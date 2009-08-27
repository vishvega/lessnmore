<?php

include('config.php');
include('db.php');
include('stats.php');

define('BCURLS_VERSION',	'1.1');

define('BCURLS_DOMAIN', 	preg_replace('#^www\.#', '', $_SERVER['SERVER_NAME']));
define('BCURLS_URL', 	str_replace('-/index.php', '', 'http://'.BCURLS_DOMAIN.$_SERVER['PHP_SELF']));

define('COOKIE_NAME', 	DB_PREFIX.'auth');
define('COOKIE_VALUE',	md5(USERNAME.PASSWORD.COOKIE_SALT));
define('COOKIE_DOMAIN', '.'.BCURLS_DOMAIN);

define('NOW', 		time());
define('YEAR',		365 * 24 * 60 * 60);

// handle login
if (isset($_POST['username']))
{
	if (md5($_POST['username'].$_POST['password'].COOKIE_SALT) == COOKIE_VALUE)
	{
		setcookie(COOKIE_NAME, COOKIE_VALUE, NOW + YEAR, '/', COOKIE_DOMAIN);
		$_COOKIE[COOKIE_NAME] = COOKIE_VALUE;
	}
}

// handle logout
if (isset($_GET['logout']))
{
	setcookie(COOKIE_NAME, '', NOW - YEAR, '/', COOKIE_DOMAIN);
	unset($_COOKIE[COOKIE_NAME]);
	header('Location:./');
}

// require login
if (!isset($_COOKIE[COOKIE_NAME]) || $_COOKIE[COOKIE_NAME] != COOKIE_VALUE)
{
	include('pages/login.php');
	exit();
}
// prolong login for another year
else
{
	setcookie(COOKIE_NAME, COOKIE_VALUE, NOW + YEAR, '/', COOKIE_DOMAIN);
}

// new shortcut
if (isset($_GET['url']) && !empty($_GET['url']))
{
	$url = $_GET['url'];
	if (!preg_match('#^[^:]+://#', $url))
	{
		$url = 'http://'.$url;
	}
	$checksum 		= sprintf('%u', crc32($url));
	$escaped_url 	= $url;
	$result = $db->prepare('SELECT id FROM '.DB_PREFIX.'urls WHERE checksum=? AND url=? LIMIT 1');
	$result->bindValue(1, (int)$checksum);
	$result->bindValue(2, $escaped_url);
	$result->execute();
	if ($result->execute())
	{
		
		// exists
		if ($row = $result->fetch(PDO::FETCH_ASSOC))
		{
			$id = $row['id'];
		}
		// create
		else
		{
			if($_GET['custom_url'])
				$custom_url = "'".$_GET['custom_url']."'";
			else
				$custom_url = "NULL";
			$db->query('INSERT INTO '.DB_PREFIX.'urls (url, checksum, custom_url) VALUES(\''.$escaped_url.'\', '.$checksum.', '.$custom_url.')');
			$id = $db->lastInsertId(DB_PREFIX."urls_id_seq");
		}
	}
	if($_GET['custom_url'])
		$new_url = BCURLS_URL.$_GET['custom_url'];
	else
		$new_url = BCURLS_URL.base_convert($id, 10, 36);
	
	if (isset($_GET['tweet']))
	{
		header('Location:http://twitter.com/?status='.urlencode($new_url));
		exit();
	}
	include('pages/done.php');
}
else if(isset($_GET['stats']))
{
	$top_urls = stats_top_urls($db);
	$top_referers = stats_top_referers($db);
	include('pages/stats.php');
}
else
{
	include('pages/add.php');
}