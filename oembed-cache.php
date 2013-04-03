<?php
/*
1）第一步：如果内容里面根本没有url，直接bypass
2）第二步：对内容摘要，查询memcached获得内容结果，有结果直接返回
3）第三步：对内容摘要，查询memcached获得没有结果的原因，如果是因为”未完整"，则bypass过去
4）第四步：这样确定内容是新的，需要处理具体的URL，

*/

define('MEMC_HOST', '127.0.0.1');
define('MEMC_PORT', 11211);

define('OEMBED_SUCCEED', 1);
define('OEMBED_FAILURE', 2);
define('OEMBED_NOTDONE', 4);

define('KEY_ERROR_STATUS', 'KEY_ERROR_STATUS');

define('CONTENT_BYPASS_PREFIX', 'CONTENT_BYPASS_PREFIX');
define('ONEBOX_RESULT_PREFIX', 	'ONEBOX_RESULT_PREFIX');

function getmem_oneboxed_form_content($ori_content)
{
	$key = md5($ori_content);
	return gem_mem($key);
}

function setmem_oneboxed_with_content($ori_content, $done_content)
{
	$key = md5($ori_content);
	set_mem($key, $done_content);
}

function check_bypass_from_content($ori_content)
{
	$key = md5($ori_content);
	$result = get_mem(CONTENT_BYPASS_PREFIX.$key);
}

function getmem_onebox_from_url($ori_url)
{
	return gem_mem(ONEBOX_RESULT_PREFIX.$ori_url);
}

function setmem_onebox_with_url($ori_url, $onebox)
{
	sem_mem(ONEBOX_RESULT_PREFIX.$ori_url, $onebox);
}

function set_mem($key, $value)
{
	$mem = new Memcache;
	$mem->connect(MEMC_HOST, MEMC_PORT);
	$mem->set($key, $value);
	$mem->close();
}

function get_mem($key)
{
	$mem = new Memcache;
	$mem->connect(MEMC_HOST, MEMC_PORT);
	$result = $mem->get($key);
	$mem->close();
	return $result;
}

function set_failure_list($ori_url)
{
	set_mem(KEY_ERROR_STATUS.$ori_url, OEMBED_FAILURE);
}

function set_succeed_list($ori_url)
{
	set_mem(KEY_ERROR_STATUS.$ori_url, OEMBED_SUCCEED);
}


function in_black_list($ori_url)
{

}
