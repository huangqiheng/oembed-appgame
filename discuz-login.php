<?php

require_once "Yaml.php";

!extension_loaded('curl') && die('The curl extension is not loaded.');    

$discuz_url = 'http://bbs.appgame.com';//论坛地址    
$login_url = $discuz_url.'/logging.php?action=login';//登录页地址    
$get_url = $discuz_url.'/my.php?item=threads'; //我的帖子    

function get_discuz_cookie($form_url, $username, $password)
{
	$post_fields = array();    
	$post_fields['loginfield'] = 'username';    
	$post_fields['loginsubmit'] = 'true';    
	$post_fields['username'] = $username;    
	$post_fields['password'] = $password;    
	$post_fields['questionid'] = 0;    
	$post_fields['answer'] = '';    
	$post_fields['seccodeverify'] = '';    

	//获取表单FORMHASH    
	$ch = curl_init($login_url);    
	curl_setopt($ch, CURLOPT_HEADER, 0);    
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    
	$contents = curl_exec($ch);    
	curl_close($ch);    
	preg_match('/<input\s*type="hidden"\s*name="formhash"\s*value="(.*?)"\s*\/>/i', $contents, $matches);    
	if(!empty($matches)) {    
		$formhash = $matches[1];    
	} else {    
		die('Not found the forumhash.');    
	}    

	//POST数据，获取COOKIE    
	$cookie_file = dirname(__FILE__) . '/cookie.txt';    
	//$cookie_file = tempnam('/tmp');    
	$ch = curl_init($login_url);    
	curl_setopt($ch, CURLOPT_HEADER, 0);    
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    
	curl_setopt($ch, CURLOPT_POST, 1);    
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);    
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);    
	curl_exec($ch);    
	curl_close($ch);    
}


//带着上面得到的COOKIE获取需要登录后才能查看的页面内容    
$ch = curl_init($get_url);    
curl_setopt($ch, CURLOPT_HEADER, 0);    
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);    
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);    
$contents = curl_exec($ch);    
curl_close($ch);    

var_dump($contents);    
?> 
