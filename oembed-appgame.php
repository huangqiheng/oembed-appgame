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
	private $max_width = 306;

	private $regex_appgame = array( 
		'main_msite'=> "#(http://([a-zA-Z0-9\-]+\.)*appgame\.com/)((?:archives|app)/)?[\d]+\.html$#i",
		'ol_msite'=>   "#(http://ol\.appgame\.com/[a-zA-Z0-9\-]+/)((?:archives|app)/)?[\d]+\.html#i",
		'zt_msite'=>   "#(http://(www\.)?appgame\.com/zt/[a-zA-Z0-9\-]+/)(.+)?(?:\?p=[\d]+|[\d]+\.html)#i"
		);

	function __construct()
	{
	    add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
	}

	public function plugins_loaded()
	{
		foreach ($regex_appgame as $key=>$value) {
			wp_embed_register_handler($key, $value,array(&$this, 'oembed_handler');
		}
	}

	public function oembed_handler($match, $attr, $url, $rattr)
	{
		$url =  $match[0];
		$post_id = get_the_id();

		if ($meta = get_post_meta($post_id, $this->prefix.$url)) {
			return $meta[0];
		} 

		$api_url = sprintf($this->api_regex, $match[1], $url);
		$res = wp_remote_get($api_url);

		if ($res['response']['code'] !== 200) {
			return ; // nothing to do on error
		}

		$res_body = $res['body']
		$res_body = str_replace("#\r\n.*$#", "", $res_body);
		$data = json_decode($res_body);
		$provider_name = $data->provider_name;
		$image = $data->thumbnail_url;
		$title = $data->title;
		$content = $data->html;

		$html  = "<div class=\'onebox-result\'>";
		$html .=   "<div class=\'source\'>";
		$html .=     "<div class=\'info\'>";
		$html .=       "<a href=\'".$url."\' target=\"_blank\">";
		$html .=         "<img class=\'favicon\' src=\"http://www.appgame.com/favicon.ico\">".$provider_name;
		$html .=       "</a>";
		$html .=     "</div>";
		$html .=   "</div>";

		$html .=   "<div class=\'onebox-result-body\'>"; if ($image) {
		$html .=     "<a href=\"".$url."\" target=\"_blank\"><img src=\"".$image."\" class=\"thumbnail\"></a>";}
		$html .=     "<h3><a href=\"".$url."\" target=\"_blank\" class=\"onebox-title\">".$title."</a></h3>";
		$html .=     $content;
		$html .=   "</div>";
		$html .=   "<div class=\'clearfix\'></div>";
		$html .= "</div>";

		$html = apply_filters("oembed-appgame-content", $html);

		update_post_meta($post_id, $this->prefix.$url, $html);
		return $html;
	}
}
// EOF
