<?php
/*
Plugin Name: oEmbed appgame
Plugin URI: https://github.com/huangqiheng/oembed-appgame
Description: Embed source from www.appgame.com.
Author: huangqiheng
Version: 0.0.1
Author URI: https://github.com/huangqiheng
*/

new oEmbedAppgame();

class oEmbedAppgame{

	private $prefix = '_appgame_';

	//任玩堂链接的正则表达式
	private $regex_appgame = array( 
		'main_msite'=> "#(http://([a-zA-Z0-9\-]+\.)*appgame\.com/)((?:archives|app)/)?[\d]+\.html$#i",
		'ol_msite'=>   "#(http://ol\.appgame\.com/[a-zA-Z0-9\-]+/)((?:archives|app)/)?[\d]+\.html#i",
		'zt_msite'=>   "#(http://(www\.)?appgame\.com/zt/[a-zA-Z0-9\-]+/)(.+)?(?:\?p=[\d]+|[\d]+\.html)#i"
		);

	//itunes链接的正则表达式
	private $regex_itunes = "#https?://itunes.apple.com(\S*)/app\S*/id(\d+)(\?mt=\d+){0,1}[\s\S]+#i";

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
		处理*.appgame.com的链接
	---------------------------------------------------------------------*/

	public function oembed_appgame_handler($match, $attr, $url, $rattr)
	{
		$ori_url =  $match[0];
		$api_prefix = $match[1];
		return $this->oembed_appgame($api_prefix, $ori_url);
	}

	public function oembed_appgame($api_prefix, $ori_url)
	{
		return $this->get_appgame_oembed_content($api_prefix, $ori_url);


		if ($title && $content && $image) {
			//保存在“缓存”里
			update_post_meta($post_id, $this->prefix.$url, $html);
		} else {
			//发现有人引用了不全信息的链接，应该通知appgame去补全

		}

		return $html;
	}

	function get_appgame_oembed_content($api_prefix, $ori_url)
	{
		if ($meta = get_post_meta(get_the_id(), $this->prefix.$ori_url)) {
			return $meta[0];
		} 

		$can_save = false;
		$res_body = $this->get_oembed_from_api ($api_prefix, $ori_url);
		$return = $this->make_oembed_template ($res_body, $ori_url, $can_save);

		if ($can_save) {
			//保存在“缓存”里
			update_post_meta($post_id, $this->prefix.$ori_url, $html);
		} else {
			//资料不全？需要通知相关人等
		}

		return $return;
	}


	function get_oembed_from_api ($api_prefix, $ori_url)
	{
		if (empty($api_prefix) || empty($ori_url)) {
			return null;
		}

		//任玩堂的oEmbed的api格式
		$api_regex = "%s?oembed=true&format=json&url=%s";
		$api_url = sprintf($api_regex, $api_prefix, $ori_url);

		$headers = array(
			"Accept: application/json", 
			"Accept-Encoding: deflate,sdch", 
			"Accept-Charset: utf-8;q=1"
			);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);

		$res = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_errno($ch);
		curl_close($ch);

		if (($err) || ($httpcode !== 200)) {
			return null;
		}

		preg_match("#{\".*\"}#ui", $res, $mm);
		$res_body = $mm[0];

		if (empty($res_body)) {
			return null;
		}

		return $res_body;
	}

	public function make_oembed_template ($res_body, $ori_url, &$can_save)
	{
		if (empty($res_body)) {
			return null;
		}

		$data = json_decode($res_body);
		if (empty($data)) {
			return null;
		}

		$favicon_url = "http://www.appgame.com/favicon.ico";
		$provider_name = $data->provider_name;
		$provider_url  = $data->provider_url;
		$image = $data->thumbnail_url;
		$title = $data->title;
		$content = $data->html;

		//截断过长的html内容
		if (mb_strlen($content) > 255) {
			//去掉html标签先
			$content = preg_replace("#<.+?>#siu", "", $content);

			//截取为最大限制长度
			if (mb_strlen($content) > 255) {
				$content = mb_substr($content, 0, 255);
			}
		}

		//构造html模板
		$html  = "<div class=\"onebox-result\">";
		$html .=   "<div class=\"source\">";
		$html .=     "<div class=\"info\">";
		$html .=       "<a href=$provider_url target=\"_blank\">";
		$html .=         "<img class=\"favicon\" src=$favicon_url>$provider_name";
		$html .=       "</a>";
		$html .=     "</div>";
		$html .=   "</div>";

		$html .=   "<div class=\"onebox-result-body\">"; if ($image) {
		$html .=     "<a href=$ori_url target=\"_blank\"><img src=$image class=\"thumbnail\"></a>";}
		$html .=     "<h3><a href=$ori_url target=\"_blank\" class=\"onebox-title\">$title</a></h3>";
		$html .=     $content;
		$html .=   "</div>";
		$html .=   "<div class=\"clearfix\"></div>";
		$html .= "</div>";

		$can_save = ($image && $title && $content);

		return $html;
	}


	/*----------------------------------------------------------------------
		wordpress评论的处理，在disqus的时候有问题
	---------------------------------------------------------------------*/

	public function add_filter_comment() 
	{
		//是否在后台可见，方便审核内容
		//if ( is_admin() ) return;

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
