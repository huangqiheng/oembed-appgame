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

	private $api_regex = "%s?oembed=true&format=json&url=%s";
	private $prefix = '_appgame_';
	private $regex_appgame = array( 
		'main_msite'=> "#(http://([a-zA-Z0-9\-]+\.)*appgame\.com/)((?:archives|app)/)?[\d]+\.html$#i",
		'ol_msite'=>   "#(http://ol\.appgame\.com/[a-zA-Z0-9\-]+/)((?:archives|app)/)?[\d]+\.html#i",
		'zt_msite'=>   "#(http://(www\.)?appgame\.com/zt/[a-zA-Z0-9\-]+/)(.+)?(?:\?p=[\d]+|[\d]+\.html)#i"
		);

	private $regex_itunes = "#https?://itunes.apple.com(\S*)/app\S*/id(\d+)(\?mt=\d+){0,1}[\s\S]+#i";

	/*----------------------------------------------------------------------
		初始化代码
	---------------------------------------------------------------------*/

	function __construct()
	{
		add_action( 'plugins_loaded', array(&$this, 'on_plugins_loaded'));
		add_action( 'init', array( $this, 'init_comment_filter' ) );
	}

	public function init_comment_filter() {
//		if ( is_admin() ) return;
		$this->add_filter_comment();
	}


	public function on_plugins_loaded()
	{
		foreach ($this->regex_appgame as $key=>$value) {
			wp_embed_register_handler($key, $value,array(&$this, 'oembed_appgame_handler'));
		}

		wp_embed_register_handler('embed_itunes', $this->regex_itunes,array(&$this, 'embed_itunes_handler'));
	}

	/*----------------------------------------------------------------------
		处理抛送过来的url
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

		$appgame_url = "http://www.appgame.com/itunes_js.php?id=".$appid."&country=".$country;
		$html = "<p><script type=\"text/javascript\" src=$appgame_url></script></p>";
		error_log($html);
		return $html;
	}

	public function oembed_appgame_handler($match, $attr, $url, $rattr)
	{
		$url =  $match[0];
		$post_id = get_the_id();

		if ($meta = get_post_meta($post_id, $this->prefix.$url)) {
			return $meta[0];
		} 

		$hdrsArr = array(); 
		$hdrsArr['Accept']='application/json,text/javascript,*/*;q=0.01'; 
		$hdrsArr['Accept-Encoding']='deflate,sdch'; 
		$hdrsArr['Accept-Language']='zh-CN,zh;q=0.8'; 
		$hdrsArr['Accept-Charset']='utf-8;q=0.7,*;q=0.3'; 

		$api_url = sprintf($this->api_regex, $match[1], $url);
		$res = wp_remote_get($api_url, array('timeout'=>10, 'headers'=>$hdrsArr));

		if (is_wp_error($res)) {
			return ; // nothing to do on error
		}

		if ($res['response']['code'] !== 200) {
			return ; // nothing to do on error
		}

		preg_match("#{\".*\"}#ui", $res['body'], $mm);
		$res_body = $mm[0];

		$data = json_decode($res_body);
		if (empty($data)) {
			return ;
		}

		$favicon_url = "http://www.appgame.com/favicon.ico";
		$provider_name = $data->provider_name;
		$provider_url  = $data->provider_url;
		$image = $data->thumbnail_url;
		$title = $data->title;
		$content = $data->html;

		if (mb_strlen($content) > 255) {
			$content = preg_replace("#<.+?>#siu", "", $content);
			if (mb_strlen($content) > 255) {
				$content = mb_substr($content, 0, 255);
			}
		}

		$html  = "<div class=\"onebox-result\">";
		$html .=   "<div class=\"source\">";
		$html .=     "<div class=\"info\">";
		$html .=       "<a href=$provider_url target=\"_blank\">";
		$html .=         "<img class=\"favicon\" src=$favicon_url>$provider_name";
		$html .=       "</a>";
		$html .=     "</div>";
		$html .=   "</div>";

		$html .=   "<div class=\"onebox-result-body\">"; if ($image) {
		$html .=     "<a href=$url target=\"_blank\"><img src=$image class=\"thumbnail\"></a>";}
		$html .=     "<h3><a href=$url target=\"_blank\" class=\"onebox-title\">$title</a></h3>";
		$html .=     $content;
		$html .=   "</div>";
		$html .=   "<div class=\"clearfix\"></div>";
		$html .= "</div>";

		$html = apply_filters("oembed-appgame-content", $html);

		//update_post_meta($post_id, $this->prefix.$url, $html);
		return $html;
	}

	public function add_filter_comment() {
		$clickable = has_filter( 'comment_text', 'make_clickable' );
		$priority = ( $clickable ) ? $clickable - 1 : 10;

		add_filter( 'comment_text', array( $this, 'oembed_comments' ), $priority);
	}

	public function oembed_comments( $comment_text ) {
		global $wp_embed, $oembed_comments;

		ksort( $wp_embed->handlers );



		add_filter( 'embed_oembed_discover', '__return_false', 999 );
		$comment_text = $wp_embed->autoembed( $comment_text );
		remove_filter( 'embed_oembed_discover', '__return_false', 999 );

		$comment_text = $this->appgame_autoembed( $comment_text );

		error_log('-------------------------');
		error_log($comment_text);
		return $comment_text;
	}

	public function appgame_autoembed( $content ) {
		return preg_replace_callback( '|^\s*(https?://[^\s"]+)\s*$|im', array(&$this, 'autoembed_callback'), $content );
	}

	public function autoembed_callback( $match ) {
		$return = $match[1];
		foreach ($this->regex_appgame as $value) {
			if (preg_match($value, $match[1], $new_match)) {
				$return = $this->oembed_appgame_handler($new_match, null, null, null);
				break;
			}
		}
		return $return;
	}

}
// EOF
