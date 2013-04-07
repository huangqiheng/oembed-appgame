<?php


define('OEMBED_CONTENTS','oembed-content-list');
define('DELIMITER_MARK', '@DELIMITER@');

function flush_post_cache($post_id=null)
{                       
        if (empty($post_id)) {
                $allposts = get_posts('numberposts=-1&post_type=post&post_status=any');
        
                foreach($allposts as $postinfo) {
                        if (delete_post_meta($postinfo->ID, OEMBED_CONTENTS)) {
                                error_log('succeed delete meta :'.$postinfo->post_title);
                        }
                } 
        } else {
                delete_post_meta($post_id, OEMBED_CONTENTS);
        }
}   

function get_post_cache($ori_url)
{
	if ($metas = get_post_meta(get_the_id(), OEMBED_CONTENTS)) {
		foreach ($metas as $meta_item) {
			list($item_url, $content) = explode(DELIMITER_MARK, $meta_item);
			if ($ori_url == $item_url) {
				return $content;
			}
		}
	}
	return null;
}

function set_post_cache($ori_url, $data)
{
	if (empty($data)) {
		return null;
	}
	
	$cached = get_post_cache($ori_url);

	if (empty($cached)) {
		$datas = array($ori_url, $data);
		$content = implode(DELIMITER_MARK, $datas);
		add_post_meta(get_the_id(), OEMBED_CONTENTS, $content);
	}
	return $data;
}
