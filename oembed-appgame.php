<?php
/*
Plugin Name: oEmbed appgame
Plugin URI: https://github.com/huangqiheng/oembed-appgame
Description: Embed source from www.appgame.com.
Author: huangqiheng
Version: 0.0.1
Author URI: https://github.com/huangqiheng
*/

require_once 'function-oembed.php';
require_once 'post-meta-cache.php';

new oEmbedAppgame();

class oEmbedAppgame{

	private $prefix = '_appgame_';

	//任玩堂链接的正则表达式
	private $regex_appgame = array( 
		'ol_msite'=>   "#(http://ol\.appgame\.com/[a-zA-Z0-9\-]+/)([a-zA-Z0-9\-]+/)*?[\d]+\.html$#i",
		'zt_msite'=>   "#(http://(www\.)?appgame\.com/zt/[a-zA-Z0-9\-]+/)(.+)?(?:\?p=[\d]+|[\d]+\.html)#i",
		'main_msite'=> "#(http://([a-zA-Z0-9\-]+\.)*appgame\.com/)((?:archives|app)/)?[\d]+\.html$#i"
		);

	//itunes链接的正则表达式
	private $regex_itunes = "#https?://itunes.apple.com(\S*)/app\S*/id(\d+)(\?mt=\d+){0,1}[\s\S]+#i";

	//bbs.appgame连接的正则表达式
	private $regex_bbs = array(
		'thread-pid'=> "#http://bbs\.appgame\.com/forum\.php\?mod=(redirect)&goto=findpost&ptid=(\d+)&pid=(\d+)#i",
		'thread-pid1'=>"#http://bbs\.appgame\.com/forum\.php\?mod=(redirect)&goto=findpost&ptid=(\d+)&pid=(\d+)&fromuid=(\d+)#i",
		'thread-url1'=>"#http://bbs\.appgame\.com/forum\.php\?mod=(viewthread)&tid=(\d+)&fromuid=(\d+)#i",
		'thread-url'=> "#http://bbs\.appgame\.com/thread-(\d+)-(\d+)-(\d+)\.html#i"
		);

	/*----------------------------------------------------------------------
		初始化代码
	---------------------------------------------------------------------*/

	function __construct()
	{
		//在非wordpress环境下，bypass掉
		if (!function_exists('add_action')) {
			return ;
		}

		//获取wordpress插件加载时机
		add_action( 'plugins_loaded', array(&$this, 'on_plugins_loaded'));

		//获取页面初始化时机
		add_action( 'init', array( $this, 'on_page_initial' ) );
	}

	public function on_plugins_loaded()
	{
		//注册appgame.com网站的embed
		foreach ($this->regex_appgame as $key=>$value) {
			wp_embed_register_handler($key, $value,array(&$this, 'oembed_appgame_handler'));
		}

		//注册itunes链接在文章内的embed
		wp_embed_register_handler('embed_itunes', $this->regex_itunes,array(&$this, 'embed_itunes_handler'));

		//注册bbs.appgame.com网站的embed
		foreach ($this->regex_bbs as $key=>$value) {
			wp_embed_register_handler($key, $value,array(&$this, 'oembed_bbs_appgame_handler'));
		}
	}

	public function on_page_initial() 
	{
		//处理评论内容
		$this->add_filter_comment();
	}

	/*----------------------------------------------------------------------
		处理itunes的链接
	---------------------------------------------------------------------*/

	public function embed_itunes_handler($match, $attr, $url, $rattr)
	{
		$country = $match[1];
		if ($country == null || $country == "") {
			$country = "us";
		} else {
			$country = substr($country, 1, 2);
		}
		$appid = $match[2];

		return $this->embed_itunes($appid, $country);
	}

	public function embed_itunes($appid, $country)
	{
		$appgame_url = "http://www.appgame.com/itunes_js.php?id=".$appid."&country=".$country;
		$html = "<p><script type=\"text/javascript\" src=$appgame_url></script></p>";
		//error_log($html);
		return $html;
	}

	/*----------------------------------------------------------------------
		处理bbs.appgame.com的链接
	---------------------------------------------------------------------*/

	public function oembed_bbs_appgame_handler($match, $attr, $url, $rattr)
	{
		$ori_url =  $match[0];

		$pid = null;
		if ($match[1] == 'redirect') {
			$pid = $match[3];
		}

		$return = get_bbspage_form_url($ori_url, $pid);

		return $return;
	}
	/*----------------------------------------------------------------------
		处理*.appgame.com的链接
	---------------------------------------------------------------------*/

	public function oembed_appgame_handler($match, $attr, $url, $rattr)
	{
		$ori_url =  $match[0];
		$api_prefix = $match[1];

		if ($content = get_post_cache($ori_url)) {
			return $content;
		} 

		$can_save = false;
		$res_body = get_oembed_from_api ($api_prefix, $ori_url);
		$return = make_oembed_template ($res_body, $ori_url, $can_save);

		//构造一个特殊的“命令”
		if (empty($return)) {
			if (preg_match("#/([\d]+?)\.html$#", $ori_url, $cmd_int)) {
                                if ($cmd_int[1] == 7777777) {
                                        flush_post_cache();
                                        error_log('flush_post_cache all succeed');
                                } else 
                                if ($cmd_init[1] == 5555555) {
                                        flush_post_cache(get_the_id());
                                        error_log('flush_post_cache one succeed');
                                }
			}
			return null;
		}

		return set_post_cache($ori_url, $return);
	}


	/*----------------------------------------------------------------------
		wordpress评论的处理，在disqus的时候有问题
	---------------------------------------------------------------------*/

	public function add_filter_comment() 
	{
		//只在后台可见，方便审核内容
		if (!is_admin()) return;

		$clickable = has_filter( 'comment_text', 'make_clickable' );
		$priority = ( $clickable ) ? $clickable - 1 : 10;

		add_filter( 'comment_text', array( $this, 'oembed_comments' ), $priority);
	}

	public function oembed_comments( $comment_text ) 
	{
		global $wp_embed, $oembed_comments;
		ksort( $wp_embed->handlers );

		add_filter( 'embed_oembed_discover', '__return_false', 999 );
		$comment_text = $wp_embed->autoembed( $comment_text );
		remove_filter( 'embed_oembed_discover', '__return_false', 999 );

		$comment_text = $this->scan_oembed_appgame( $comment_text );

		//error_log('-------------------------');
		//error_log($comment_text);
		return $comment_text;
	}

	public function scan_oembed_appgame( $content ) 
	{
		return preg_replace_callback( '|^\s*(https?://[^\s"]+)\s*$|im', array(&$this, 'oembed_appgame_callback'), $content );
	}

	public function oembed_appgame_callback( $match ) 
	{
		$return = $match[1];
		foreach ($this->regex_appgame as $value) {
			if (preg_match($value, $match[1], $new_match)) {
				$ori_url =  $new_match[0];
				$api_prefix = $new_match[1];
				$return = $this->oembed_appgame($api_prefix, $ori_url);
				break;
			}
		}
		return $return;
	}
}
// EOF
