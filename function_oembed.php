<?php

require_once 'nokorigi.php';

function process_post_by_display($post) 
{
	$post = process_itunes_link($post);
	$post = process_appgame_link($post);
	return $post;
}

//http://bbs.appgame.com/forum.php?mod=redirect&goto=findpost&ptid=41501&pid=253566&fromuid=10434
//http://bbs.appgame.com/forum.php?mod=viewthread&tid=41536&fromuid=10434
function process_bbs_appgame_link($post)
{

}

function get_bbspage_form_url($ori_url, $pid)
{
	$html = gzdecode(file_get_contents($ori_url));
	//<table id="pid256299" summary="pid256299" cellspacing="0" cellpadding="0">
	$saw = new nokogiri($html);

	var_dump($saw->get("table[@id=pid".$pid."]")->toArray());


}

function process_appgame_link($post)
{
        $regex_appgame = array( 
                "#<a href=\"((http://ol\.appgame\.com/[a-zA-Z0-9\-]+/)([a-zA-Z0-9\-]+/)*?[\d]+\.html)[\s\S]+?</a>#i",
                "#<a href=\"((http://(www\.)?appgame\.com/zt/[a-zA-Z0-9\-]+/)(.+)?(?:\?p=[\d]+|[\d]+\.html))[\s\S]+?</a>#i",
                "#<a href=\"((http://([a-zA-Z0-9\-]+\.)*appgame\.com/)((?:archives|app)/)?[\d]+\.html)[\s\S]+?</a>#i"
                );

	return preg_replace_callback( $regex_appgame, 'oembed_appgame_callback', $post);
}

function oembed_appgame_callback( $match )
{
	$ori_url =  $match[1];
	$api_prefix = $match[2];
	return get_appgame_oembed_content($api_prefix, $ori_url);
}

function get_cache_file_name($ori_url)
{
	$search = array(':','.',',',';','/','|','?','&','#','@','!','+','=');
	$url_file = str_replace($search, '-', $ori_url);
	return "app/cache-".$url_file.".txt";
}

function get_cache_data($ori_url)
{
	$appfile = get_cache_file_name($ori_url);

	if (!file_exists($appfile)) {
		return null;
	}

	return file_get_contents($appfile);
}

function put_cache_data($ori_url, $data)
{
	$appfile = get_cache_file_name($ori_url);

	$fhandler = fopen($appfile, 'a');
	if ($fhandler && fwrite($fhandler, trim($data))) {
		fclose($fhandler);
	}
	return $data;
}

function get_appgame_oembed_content($api_prefix, $ori_url)
{
	if ($res = get_cache_data($ori_url)) {
		return $res;
	}

	$can_save = false;
	$res_body = get_oembed_from_api ($api_prefix, $ori_url);
	$return = make_oembed_template ($res_body, $ori_url, $can_save);

	if ($can_save) {
		put_cache_data($ori_url, $return);
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

function remove_html_tag($content)
{
	$ori_content = $content;

	//去掉html标签先
	$ori_content = preg_replace("#<([^<>].*|(?R))*>#", "", $ori_content);

	if (empty($ori_content)) {
		$content = preg_replace("#<.*>#", "", $content);
	} else {
		$content = $ori_content;
	}

	//截取为最大限制长度
	if (mb_strlen($content) > 255) {
		$content = mb_substr($content, 0, 255);
	}
	return $content;
}

function make_oembed_template ($res_body, $ori_url, &$can_save)
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
        mb_internal_encoding("UTF-8");

	if (mb_strlen($content) > 255) {
		$content = remove_html_tag($content);
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

function process_itunes_link($post) 
{
	preg_match_all("/<a href=\"(http|https):\/\/itunes.apple.com(\S*)\/app\S*\/id(\d+)(\?mt\=\d+){0,1}[\s\S]+<\/a>/i", $post, $matches);
	
	if ($matches == null || $matches[0] == null) {
		return $post;
	}
	
	for ($i=0; $i< count($matches[0]); $i++) {
		$link = $matches[0][$i];
		$protocol = $matches[1][$i];
		$country = $matches[2][$i];
		if ($country == null || $country == "") {
			$country = "us";
		} else {
			$country = substr($country, 1, 2);
		}
		$appid = $matches[3][$i];
		
		$appfile = "app/".$appid."_".$country.".txt";
		if (!file_exists($appfile)) {
			
			$pageContents = file_get_contents("http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?id=" . $appid . "&country=" . $country);
			
			if ($pageContents != null && trim($pageContents) != "") {
				// 写缓存
				$fhandler = fopen($appfile, 'a');
				if ($fhandler && fwrite($fhandler, trim($pageContents))) {
					fclose($fhandler);
				}
				
				return str_replace($link, process_link($link) . get($pageContents, $country), $post);
			}
			
			break;
		}
		
		return str_replace($link,  get(file_get_contents($appfile), $country) . process_link($link), $post);
	}
	return $post;
}


function process_link($link) 
{
	preg_match_all("/\"(http|https):\/\/itunes.apple.com(\S*)\/app\S*\/id(\d+)(\?mt\=\d+){0,1}[^\"]*\"/i", $link, $matches);
	
	if ($matches == null || $matches[0] == null) {
		return $link;
	}
	
	return str_replace($matches[0][0], "\"" . get_url(substr($matches[0][0], 1, strlen($matches[0][0])-2)) . "\"", $link);
}

function get_url($url) 
{
	$n_url = $url . "&partnerId=30";
	
	return "http://click.linksynergy.com/fs-bin/stat?id=pSekzAypeyg&offerid=146261&type=3&subid=0&tmpid=1826&RD_PARM1=" . urlencode(urlencode($n_url));
}


function get($appcontent, $country) 
{
	//$apps_con = file_get_contents("http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?id=".$appid."&country=".$country);
	
	$obj = json_decode($appcontent);
	$apps_array = $obj->{"results"}[0];
	//$app['a']['app_id']= $appid;
	
	$view_url = get_url($apps_array->{'trackViewUrl'});
	
	//$view_url = $apps_array->{'trackViewUrl'};
	$app_name = $apps_array->{'trackName'};
	$app_logo = substr($apps_array->{'artworkUrl512'},0,-4).'.175x175-75.jpg';
	$app_logo = str_replace('.512x512-75','',$app_logo);
	
	$sign = $country == "cn" || $country == "jp" ? "￥" : "$";
	
	$app_price = ($apps_array->{'price'}=='0'||$apps_array->{'price'}=='0.00')?'Free':$sign.$apps_array->{'price'}."（" . $country . "）";
	$order   = array("\r\n", "\n", "\r");
	$replace = '<br />';
	$app_description = str_replace($order, $replace, $apps_array->{'description'});
	//$app_version = $apps_array['version'];
	//$cpn_id = $apps_array['artistId'];
	//$app_category = $apps_array['primaryGenreName'];
	//$app_rating = $apps_array['trackContentRating'];
	//$app_current_rating_count = $apps_array['userRatingCount'];
	$app_screenshots = array_merge($apps_array->{'screenshotUrls'},$apps_array->{'ipadScreenshotUrls'});
	//$app_releaseDate = date('Y-m-d',strtotime($apps_array['releaseDate']));
	$app_language = implode(',',$apps_array->{'languageCodesISO2A'});
	
	
	$features = $apps_array->{"features"};
	$str_features = "";
	$c = 0;
	if ($features != null) {
		$c = count($features);
	}
	for ($i = 0; $i < $c; $i++) {
		$str_features .= $features[$i];
		if ($i < $c - 1) {
			$str_features .= "|";
		}
	}
	$devices = $apps_array->{"supportedDevices"};
	$str_devices = "";
	$c = 0;
	if ($devices != null) {
		$c = count($devices);
	}
	for ($i = 0; $i < $c; $i++) {
		$str_devices .= $devices[$i];
		if ($i < $c - 1) {
			$str_devices .= "|";
		}
	}
	
	$device = "";
	$str = strtolower($str_devices);
	if (!(strpos($str_features, "iosUniversal") === false)) {
		$device = "通用版";
	} else if ($str == "all" || !(strpos($str, "iphone") === false)) {
		$device = "iPhone";
	} else if (!(strpos($str, "ipad") === false)) {
		$device = "iPad";
	}
	
	if ($device == "") {
		$str = strtolower($apps_array->{"kind"});
		if (!(strpos($str, "mac") == false)) {
			$device = "Mac";
		}
	}
	if ($device == "") {
		$device = "iPhone";
	}
	
	//$app_devices = implode(',',$apps_array->{'supportedDevices'});
	//$primaryGenreId = $apps_array['primaryGenreId'];
	$app_size = number_format($apps_array->{'fileSizeBytes'}/1024/1024, 2, '.', '').'M';
	$seller_name = $apps_array->{'sellerName'};
	
	$screenhots = "";
	
	$i = 0;
	foreach ($app_screenshots as $key=>$v) {
		if ($i >= 4) {
			break;
		}
		$key++;
		$i++;
		$v = substr($v, 0, -3) . ($device == "iPad" ? "480x480" : "320x480") . "-75.jpg";
		$v = str_replace('.1024x1024-65','',$v);
		$screenhots .= "<a href='$v' target='_blank'><img src='$v' alt='$app_name - Screen shot-$key' /></a>";
	}
	global $it_index;
	if (!isset($it_index)) {
		$it_index = 1;
	} else {
		$it_index += 1;
	}
$content = <<<EOT
<div class="bbs_appshow">
	<div class="appshow_title">$app_name</div>
	<div class="appshow_des" id="thecon$it_index" style="height:180px;overflow-y:hidden;">
		<a href='$view_url' title='前往iTunes下载' target='_blank'>
		<img src='$app_logo' alt='$app_name' />
		</a>
		$app_description
	</div>
	<a href="javascript:void(0)" id="show$it_index" style="display:block" onclick="document.getElementById('thecon$it_index').style.height='100%';document.getElementById('hidden$it_index').style.display='block';document.getElementById('show$it_index').style.display='none';">显示全部</a>
	<a href="javascript:void(0)" id="hidden$it_index" style="display:none;" onclick="document.getElementById('thecon$it_index').style.height='180px';document.getElementById('hidden$it_index').style.display='none';document.getElementById('show$it_index').style.display='block';">隐藏部分</a>
	<div class="appshow_screen">$screenhots</div>
	<div class="appstyle_container">
	<div class="appstyle_container2">
		<div class="appstyle_logo"><div class="appstyle-logomask"><img src="http://www.appgame.com/source/rating/app-style-logocover.png" alt="itunes logo mask" /></div><div class="appstyle-logoimg"><img src='$app_logo' alt='$app_name' style="width:72px;height:72px;" /></div></div>
		<div class="appstyle_button">
		<span class="appstyle_newprice">$app_price</span><br /><a href='$view_url' target="_blank" title="前往App Store下载"><img src="http://www.appgame.com/source/rating/app-style-download.jpg" alt="Download" /></a>
		</div>
		<div class="appstyle_des">
            	<span class="appstyle_name">$app_name</span><br />
                适用设备 &nbsp; <span class="appstyle_time">$device</span><br />
            	发行厂商 &nbsp; <span class="appstyle_size">$seller_name</span><br />
            	软件大小 &nbsp; <span class="appstyle_size">$app_size</span><br />
		</div>
		<div class="appstyle-clear"></div>    
	</div>
	</div>
</div>
EOT;
	
	return $content;
}
?>
