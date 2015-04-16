<?php

/**
* Blog posting methods
* 
* @package		YF
* @author		YFix Team <yfix.dev@gmail.com>
* @version		1.0
*/
class yf_blog_posting {

	/**
	* Constructor
	*/
	function _init () {
		$this->SETTINGS		= &module('blog')->SETTINGS;
		$this->USER_RIGHTS	= &module('blog')->USER_RIGHTS;
		if (module('blog')->ALLOW_TAGGING) {
			$this->TAGS_OBJ = module("tags");
		}
	}
	
	/**
	* Add post method
	*/
	function _add_post() {
		if (empty(main()->_user_info)) {
			return _error_need_login();
		}
		if ($_ban_error = module('blog')->_ban_check()) {
			return $_ban_error;
		}
		// Get current blog settings
		module('blog')->BLOG_SETTINGS = module('blog')->_get_user_blog_settings(main()->USER_ID);
		// Prepare user custom categories
		$custom_cats_info = module('blog')->_custom_cats_into_array(module('blog')->BLOG_SETTINGS["custom_cats"]);
		foreach ((array)$custom_cats_info as $custom_cat_id => $info) {
			$custom_cats_for_box[$custom_cat_id + 1] = $info["name"];
		}
		
		$FRIENDS_OBJ = module("friends");
		
		// Check posted data and save
		if (!empty($_POST["go"])) {
			// Fix and get max second id
			$max_id2 = module('blog')->_fix_id2(main()->USER_ID);

			$_POST["post_title"]	= substr($_POST["post_title"], 0, module('blog')->MAX_POST_TITLE_LENGTH);
			$_POST["post_text"]		= substr($_POST["post_text"], 0, module('blog')->MAX_POST_TEXT_LENGTH);
			$_POST["mode_text"]		= substr($_POST["mode_text"], 0, module('blog')->MAX_MODE_TEXT_LENGTH);
			if (empty($_POST["post_title"])) {
				_re("Post title required");
			}
			if (empty($_POST["post_text"])) {
				_re("Post text required");
			}
			// Do check captcha (if needed)
			if (module('blog')->USE_CAPTCHA) {
				_class('blog')->_captcha_check();
			}
			// Load attached_image
			$attach_image = !empty($_FILES["attach_image"]["size"]) ? $this->_load_attach_image() : "";
			// Check for errors
			if (!common()->_error_exists()) {
				// Check text fields
				$_POST["post_title"]	= _filter_text($_POST["post_title"]);
				$_POST["post_text"]		= _filter_text($_POST["post_text"]);
				$_POST["mode_text"]		= _filter_text($_POST["mode_text"]);
				// Do close BB Codes (if needed)
				if ($this->USE_BB_CODES) {
					$BB_CODES_OBJ = _class("bb_codes");
					if (is_object($BB_CODES_OBJ)) {
						$_POST["post_text"] = $BB_CODES_OBJ->_force_close_bb_codes($_POST["post_text"]);
					}
				}
				$mood = isset(module('blog')->_moods[$_POST["mood"]]) && $_POST["mood"] != 1 ? $_POST["mood"] : $_POST["mood2"];

				// if post in community
				if(!empty($_POST["community_select_box"])){
				
					$community_info = db()->query_fetch("SELECT id,user_id,moderated FROM ".db('community')." WHERE id=".intval($_POST["community_select_box"]));
					
					$this->_check_community_permissions($community_info["user_id"]);
					
					if(common()->_error_exists()){
						return _e();
					}

					if($community_info["moderated"] == "1"){
						if($GLOBALS["community_user_settings"]["unmoderated"] == "1"){
							$active = 1;
						}else{
							$active = 0;
						}
					}else{
						$active = 1;
					}
					
					$user_id = $community_info["user_id"];
					$poster_id = intval(main()->USER_ID);
				}else{
					$user_id = intval(main()->USER_ID);
					$poster_id = "";
					$active = 1;
				}
				
				if(($_POST["privacy"] == "4") || ($_POST["privacy"] == "5")){
					$mask = $FRIENDS_OBJ->_ids_to_mask($_POST["group"]);
				}else{
					$mask = "0";
				}
				
				// Generate SQL				
				db()->INSERT("blog_posts", array(
					"id2"				=> intval($max_id2 + 1),
					"user_id"			=> $user_id,
					"poster_id"			=> $poster_id,
					"user_name"			=> _es(_display_name(main()->_user_info)),
					"cat_id"			=> _es($_POST["cat_id"]),
					"title"				=> _es($_POST["post_title"]),
					"text"				=> _es($_POST["post_text"]),
					"add_date"			=> time(),
					"ip"				=> _es(common()->get_ip()),
					"attach_image"		=> _es($attach_image),
					"mode_type"			=> intval($_POST["mode_type"]),
					"mode_text"			=> _es($_POST["mode_text"]),
					"mood"				=> _es($mood),
					"privacy"			=> intval($_POST["privacy"]),
					"allow_comments"	=> intval($_POST["allow_comments"]),
					"custom_cat_id"		=> intval($_POST["custom_cat_id"]),
					"active"			=> $active,
					"mask"				=> $mask,
				));

				$RECORD_ID = db()->INSERT_ID();

				// Save tags 
				if ($_POST["tags"]) {
					$this->TAGS_OBJ->_save_tags($_POST["tags"], $RECORD_ID, 'blog');
				}

				module('blog')->_update_all_stats();
				common()->_add_activity_points(main()->USER_ID, "blog_post", strlen($_POST["post_text"]), $RECORD_ID);
				db()->update('user', array('last_update' => time()), main()->USER_ID);
				_class_safe("user_stats")->_update(array("user_id" => main()->USER_ID));
				module('blog')->_do_ping($RECORD_ID, main()->USER_ID);
				if(!empty($_POST["community_select_box"])){ 
					return js_redirect("./?object=community&action=view&id=".$community_info["id"]);
				}else{
					return js_redirect("./?object=".'blog'."&action=show_posts"._add_get(array("page")));
				}
			} else {
				$error_message = _e();
			}
		}
		// Prepare privacy and allow comments data
		if (module('blog')->BLOG_SETTINGS["privacy"] > 1) {
			foreach ((array)module('blog')->_privacy_types2 as $k => $v) {
				if ($k != 0 && $k <= module('blog')->BLOG_SETTINGS["privacy"]) {
					unset(module('blog')->_privacy_types2[$k]);
				}
			}
		}
		if (module('blog')->BLOG_SETTINGS["allow_comments"] > 1) {
			foreach ((array)module('blog')->_comments_types2 as $k => $v) {
				if ($k != 0 && $k <= module('blog')->BLOG_SETTINGS["allow_comments"]) {
					unset(module('blog')->_comments_types2[$k]);
				}
			}
		}
		
		$OBJ = module("community");
		$community = is_object($OBJ) ? $OBJ->_get_community_with_allow_posting_for_user(main()->USER_ID) : "";
		
		$first_element = array("0" => t("In my blog"));
		$projects = my_array_merge($first_element, $community);
		$community_select_box = common()->select_box("community_select_box", $projects, $_POST["community_select_box"], false, 2, "", false);	
		
		$friends_groups = $FRIENDS_OBJ->_get_friends_groups(main()->USER_ID);
		
		foreach ((array)$friends_groups as $friends_group){
			$friends_group_box .= '<input type="checkbox" name="group['.$friends_group["id2"].']" value="'.$friends_group["id2"].'"> '.$friends_group["title"].'<br />';
		}
		
		// Show form
		if (empty($_POST["go"]) || !empty($error_message)) {
			$replace = array(
				"form_action"		=> "./?object=".'blog'."&action=".$_GET["action"]._add_get(array("page")),
				"error_message"		=> $error_message,
				"max_attach_size"	=> intval(module('blog')->MAX_IMAGE_SIZE),
				"max_width"			=> intval(module('blog')->ATTACH_LIMIT_X),
				"max_height"		=> intval(module('blog')->ATTACH_LIMIT_Y),
				"max_post_title"	=> intval(module('blog')->MAX_POST_TITLE_LENGTH),
				"max_post_text"		=> intval(module('blog')->MAX_POST_TEXT_LENGTH),
				"max_mode_text"		=> intval(module('blog')->MAX_MODE_TEXT_LENGTH),
				"post_title"		=> _prepare_html($_POST["post_title"]),
				"post_text"			=> _prepare_html($_POST["post_text"]),
				"blog_cats_box"		=> !module('blog')->HIDE_GENERAL_CATS ? module('blog')->_box("cat_id", $_POST["cat_id"]) : "",
				"mode_type_box"		=> module('blog')->_box("mode_type", $_POST["mode_type"]),
				"mood_box"			=> module('blog')->_box("mood", $_POST["mood"]),
				"privacy_box"		=> module('blog')->_box("privacy2", $_POST["privacy"]),
				"allow_comments_box"=> module('blog')->_box("allow_comments2", $_POST["allow_comments"]),
				"mood2"				=> !in_array($_POST["mood"], (array)module('blog')->_moods) ? _prepare_html($_POST["mood"]) : "",
				"mode_text"			=> _prepare_html($_POST["mode_text"]),
				"add_date"			=> date("Y-m-d H:i:s", !empty($_POST["add_date"]) ? strtotime($_POST["add_date"]) : time()),
				"back_url"			=> "./?object=".'blog'."&action=show_posts"._add_get(array("page")),
				"user_id"			=> intval(main()->USER_ID),
				"stpl_for_edit"		=> 0,
				"custom_cats_box"	=> !empty($custom_cats_for_box) ? common()->select_box("custom_cat_id", array_merge(array(""), $custom_cats_for_box), $_POST["custom_cat_id"], false, 2, "", false) : "",
				"edit_settings_link"=> "./?object=".'blog'."&action=settings"._add_get(array("page")),
				"use_captcha"		=> intval((bool)module('blog')->USE_CAPTCHA),
				"captcha_block"		=> _class('blog')->_captcha_block(),
				"bb_codes_block"	=> module('blog')->USE_BB_CODES ? _class("bb_codes")->_display_buttons(array("unique_id" => "bb_text", "youtube" => 1)) : "",
				"tags"				=> $_POST["tags"],
				"max_num_tags"		=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->TAGS_PER_OBJ : "",
				"min_tag_len"		=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->MIN_KEYWORD_LENGTH : "",
				"max_tag_len"		=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->MAX_KEYWORD_LENGTH : "",
				"community_select_box"	=> $community_select_box,
				"friends_group_box"		=> $friends_group_box,

			);
			$body = tpl()->parse('blog'."/edit_post_form", $replace);
		}
		return $body;
	}

	/**
	* Edit post method
	*/
	function _edit_post () {
		if (empty(main()->_user_info)) {
			return _error_need_login();
		}
		
		if ($_ban_error = module('blog')->_ban_check()) {
			return $_ban_error;
		}
		$_GET["id"] = intval($_GET["id"]);
		// Try to get given post info
		$sql = "SELECT * FROM ".db('blog_posts')." WHERE ";
		if (module('blog')->HIDE_TOTAL_ID) {
			$sql .= " id2=".intval($_GET["id"])." AND user_id=".intval($GLOBALS['HOSTING_ID'] ? $GLOBALS['HOSTING_ID'] : main()->USER_ID);
		} else {
			$sql .= " id=".intval($_GET["id"]);
		}
		$post_info = db()->query_fetch($sql);
		if (empty($post_info["id"])) {
			return _e("No such post!");
		}
		
		//if this post in community
		$is_community = $this->_check_community_permissions($post_info["user_id"]);
		
		if(common()->_error_exists()){
			return _e();
		}
		
		$is_community?$post_info["user_id"] = $post_info["poster_id"]:"";
		
		if ($post_info["user_id"] != main()->USER_ID) {
			return _e("Not your post!");
		}
		// Get current blog settings
		module('blog')->BLOG_SETTINGS = module('blog')->_get_user_blog_settings(main()->USER_ID);
		// Prepare user custom categories
		$custom_cats_info = module('blog')->_custom_cats_into_array(module('blog')->BLOG_SETTINGS["custom_cats"]);
		foreach ((array)$custom_cats_info as $custom_cat_id => $info) {
			$custom_cats_for_box[$custom_cat_id + 1] = $info["name"];
		}
		
		$FRIENDS_OBJ = module("friends");
		
		// Check posted data and save
		if (!empty($_POST["go"])) {
			// Fix and get max second id
			$max_id2 = module('blog')->_fix_id2(main()->USER_ID);

			// Save tags 
			if (isset($_POST["tags"])) {
				$this->TAGS_OBJ->_save_tags($_POST["tags"], $post_info["id"], 'blog');
			}

			$_POST["post_title"]	= substr($_POST["post_title"], 0, module('blog')->MAX_POST_TITLE_LENGTH);
			$_POST["post_text"]		= substr($_POST["post_text"], 0, module('blog')->MAX_POST_TEXT_LENGTH);
			$_POST["mode_text"]		= substr($_POST["mode_text"], 0, module('blog')->MAX_MODE_TEXT_LENGTH);
			if (empty($_POST["post_title"])) {
				_re("Post title required");
			}
			if (empty($_POST["post_text"])) {
				_re("Post text required");
			}
			// Do check captcha (if needed)
			if (module('blog')->USE_CAPTCHA) {
				_class('blog')->_captcha_check();
			}
			// Try to get new date
			$_POST["add_date"] = !empty($_POST["add_date"]) ? strtotime($_POST["add_date"]) : 0;
			// Load attached_image
			$attach_image = !empty($_FILES["attach_image"]["size"]) ? $this->_load_attach_image($post_info["attach_image"]) : "";
			// Check for errors
			if (!common()->_error_exists()) {
				// Check text fields
				$_POST["post_title"]	= _filter_text($_POST["post_title"]);
				$_POST["post_text"]		= _filter_text($_POST["post_text"]);
				$_POST["mode_text"]		= _filter_text($_POST["mode_text"]);
				// Do close BB Codes (if needed)
				if ($this->USE_BB_CODES) {
					$BB_CODES_OBJ = _class("bb_codes");
					if (is_object($BB_CODES_OBJ)) {
						$_POST["post_text"] = $BB_CODES_OBJ->_force_close_bb_codes($_POST["post_text"]);
					}
				}
				$mood = isset(module('blog')->_moods[$_POST["mood"]]) && $_POST["mood"] != 1 ? $_POST["mood"] : $_POST["mood2"];
				
				if($GLOBALS["community_info"]["moderated"] == "1"){
					if($GLOBALS["community_user_settings"]["unmoderated"] == "1"){
						$active = 1;
					}else{
						$active = 0;
					}
				}else{
					$active = 1;
				}
				
				if(($_POST["privacy"] == "4") || ($_POST["privacy"] == "5")){
					$mask = $FRIENDS_OBJ->_ids_to_mask($_POST["group"]);
				}else{
					$mask = "0";
				}
				
				// Generate SQL
				$query_array = array(
					"cat_id"			=> intval($_POST["cat_id"]), 
					"mode_type"			=> intval($_POST["mode_type"]),
					"mode_text"			=> _es($_POST["mode_text"]),
					"mood"				=> _es($mood),
					"privacy"			=> intval($_POST["privacy"]),
					"allow_comments"	=> intval($_POST["allow_comments"]),
					"custom_cat_id"		=> intval($_POST["custom_cat_id"]),
					"title"				=> _es($_POST["post_title"]),
					"text"				=> _es($_POST["post_text"]),
					"mask"				=> $mask,
				);
				
				if (!empty($_POST["add_date"])) {
					$query_array["add_date"] = intval($_POST["add_date"]);
				}
				if (!empty($attach_image)) {
					$query_array["attach_image"] = _es($attach_image);
				}

				if ($active == 0) {
					$query_array["active"] = "0";
				}

				db()->UPDATE("blog_posts", $query_array, "id=".intval($post_info["id"]));
				// Synchronize all blogs stats
				module('blog')->_update_all_stats();
				// Last update
				db()->update('user', array("last_update"=>time()), main()->USER_ID);
				// Update user stats
				_class_safe("user_stats")->_update(array("user_id" => main()->USER_ID));
				// Return user back
				
				//if this post in community
				if($is_community){
					return js_redirect("./?object=blog&action=show_single_post&id=".$post_info["id"]);
				}
				
				return js_redirect("./?object=".'blog'."&action=show_posts"._add_get(array("page")));
			} else $error_message = _e();
		} else {
			$_POST["post_title"]		= $post_info["title"];
			$_POST["post_text"]			= $post_info["text"];
			$_POST["add_date"]			= $post_info["add_date"];
			$_POST["custom_cat_id"]		= $post_info["custom_cat_id"];
			$_POST["cat_id"]			= $post_info["cat_id"];
			$_POST["mode_type"]			= $post_info["mode_type"];
			$_POST["mode_text"]			= $post_info["mode_text"];
			$_POST["mood"]				= isset(module('blog')->_moods[$post_info["mood"]]) && $_POST["mood"] != 1 ? $post_info["mood"] : "";
			$_POST["mood2"]				= !isset(module('blog')->_moods[$post_info["mood"]]) || $_POST["mood"] == 1 ? $post_info["mood"] : "";
			$_POST["privacy"]			= $post_info["privacy"];
			$_POST["allow_comments"]	= $post_info["allow_comments"];
		}
		// Prepare privacy and allow comments data
		if (module('blog')->BLOG_SETTINGS["privacy"] > 1) {
			foreach ((array)module('blog')->_privacy_types2 as $k => $v) {
				if ($k != 0 && $k <= module('blog')->BLOG_SETTINGS["privacy"]) {
					unset(module('blog')->_privacy_types2[$k]);
				}
			}
		}
		if (module('blog')->BLOG_SETTINGS["allow_comments"] > 1) {
			foreach ((array)module('blog')->_comments_types2 as $k => $v) {
				if ($k != 0 && $k <= module('blog')->BLOG_SETTINGS["allow_comments"]) {
					unset(module('blog')->_comments_types2[$k]);
				}
			}
		}
		// Show form
		if (empty($_POST["go"]) || !empty($error_message)) {
			// Prepare attachment
			$attach_web_path = "";
			if (!empty($post_info["attach_image"])) {
				$attach_web_path	= module('blog')->_attach_web_path($post_info);
				$attach_fs_path		= module('blog')->_attach_fs_path($post_info);
				if (!file_exists($attach_fs_path)) {
					$attach_web_path = "";
				}
			}
			
			if(!empty($post_info["mask"])){
				$select_friends_groups = $FRIENDS_OBJ->_mask_to_ids($post_info["mask"]);
			}
			
			$friends_groups = $FRIENDS_OBJ->_get_friends_groups(main()->USER_ID);
			
			foreach ((array)$friends_groups as $friends_group){
				$friends_group_box .= '<input type="checkbox" name="group['.$friends_group["id2"].']" value="'.$friends_group["id2"].'" '.(in_array($friends_group["id2"], (array)$select_friends_groups)?'checked':'').'> '.$friends_group["title"].'<br />';
			}
			
			// Process template
			$replace = array(
				"form_action"		=> "./?object=".'blog'."&action=".$_GET["action"]."&id=".$_GET["id"]._add_get(array("page")),
				"error_message"		=> $error_message,
				"attach_image_src"	=> $attach_web_path,
				"del_image_link"	=> "./?object=".'blog'."&action=delete_attach_image&id=".$_GET["id"]._add_get(array("page")),
				"max_attach_size"	=> intval(module('blog')->MAX_IMAGE_SIZE),
				"max_width"			=> intval(module('blog')->ATTACH_LIMIT_X),
				"max_height"		=> intval(module('blog')->ATTACH_LIMIT_Y),
				"max_post_title"	=> intval(module('blog')->MAX_POST_TITLE_LENGTH),
				"max_post_text"		=> intval(module('blog')->MAX_POST_TEXT_LENGTH),
				"max_mode_text"		=> intval(module('blog')->MAX_MODE_TEXT_LENGTH),
				"post_title"		=> _prepare_html($_POST["post_title"]),
				"post_text"			=> _prepare_html($_POST["post_text"]),
				"blog_cats_box"		=> !module('blog')->HIDE_GENERAL_CATS ? module('blog')->_box("cat_id", $_POST["cat_id"]) : "",
				"mode_type_box"		=> module('blog')->_box("mode_type", $_POST["mode_type"]),
				"mood_box"			=> module('blog')->_box("mood", $_POST["mood"]),
				"privacy_box"		=> module('blog')->_box("privacy2", $_POST["privacy"]),
				"allow_comments_box"=> module('blog')->_box("allow_comments2", $_POST["allow_comments"]),
				"mood2"				=> _prepare_html($_POST["mood2"]),
				"mode_text"			=> _prepare_html($_POST["mode_text"]),
				"disable_comments"	=> intval((bool) $_POST["disable_comments"]),
				"add_date"			=> date("Y-m-d H:i:s", !empty($_POST["add_date"]) ? $_POST["add_date"] : time()),
				"back_url"			=> "./?object=".'blog'."&action=show_single_post&id=".$_GET["id"]._add_get(array("page")),
				"user_id"			=> intval(main()->USER_ID),
				"stpl_for_edit"		=> 1,
				"custom_cats_box"	=> !empty($custom_cats_for_box) ? common()->select_box("custom_cat_id", array_merge(array(""), $custom_cats_for_box), $_POST["custom_cat_id"], false, 2, "", false) : "",
				"edit_settings_link"=> "./?object=".'blog'."&action=settings"._add_get(array("page")),
				"use_captcha"		=> intval((bool)module('blog')->USE_CAPTCHA),
				"captcha_block"		=> _class('blog')->_captcha_block(),
				"bb_codes_block"	=> module('blog')->USE_BB_CODES ? _class("bb_codes")->_display_buttons(array("unique_id" => "bb_text", "youtube" => 1)) : "",
				"tags"				=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->_collect_tags($post_info["id"], 'blog') : "",
				"max_num_tags"		=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->TAGS_PER_OBJ : "",
				"min_tag_len"		=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->MIN_KEYWORD_LENGTH : "",
				"max_tag_len"		=> is_object($this->TAGS_OBJ) ? $this->TAGS_OBJ->MAX_KEYWORD_LENGTH : "",
				"community_select_box"	=> "",
				"friends_group_box"		=> $friends_group_box,

			);
			$body = tpl()->parse('blog'."/edit_post_form", $replace);
		}
		return $body;
	}

	/**
	* Delete post method
	*/
	function _delete_post () {
		if (empty(main()->_user_info)) {
			return _error_need_login();
		}
		if ($_ban_error = module('blog')->_ban_check()) {
			return $_ban_error;
		}
		$_GET["id"] = intval($_GET["id"]);
		// Get post info
		$sql = "SELECT * FROM ".db('blog_posts')." WHERE ";
		if (module('blog')->HIDE_TOTAL_ID) {
			$sql .= " id2=".intval($_GET["id"])." AND user_id=".intval($GLOBALS['HOSTING_ID'] ? $GLOBALS['HOSTING_ID'] : main()->USER_ID);
		} else {
			$sql .= " id=".intval($_GET["id"]);
		}
		// Try to get given post info
		$post_info = db()->query_fetch($sql);
		if (empty($post_info["id"])) {
			return _e("No such post!");
		}
		
		$is_community = $this->_check_community_permissions($post_info["user_id"]);

		if (common()->_error_exists()) {
			return _e();
		}
		$post_info_real_user_id = $is_community ? $post_info["poster_id"] : $post_info["user_id"];
		
		if ($post_info_real_user_id != main()->USER_ID) {
			return _e("Not your post!");
		}
		// Fix and get max second id
		$max_id2 = module('blog')->_fix_id2(main()->USER_ID);
		// Delete image
		$attach_fs_path = "";
		if (!empty($post_info["attach_image"])) {
			$attach_fs_path = module('blog')->_attach_fs_path($post_info);
		}
		if ($attach_fs_path && file_exists($attach_fs_path)) {
			unlink($attach_fs_path);
		}
		db()->query("DELETE FROM ".db('blog_posts')." WHERE id=".intval($post_info["id"])." AND user_id='".intval($post_info["user_id"])."' LIMIT 1");
		db()->query("DELETE FROM ".db('comments')." WHERE object_name='"._es('blog')."' AND object_id=".intval($post_info["id"]));
		db()->update('user', array('last_update' => time()), main()->USER_ID);
		module('blog')->_update_all_stats();
		common()->_remove_activity_points($post_info["user_id"], "blog_post", $post_info["id"]);
		_class_safe("user_stats")->_update(array("user_id" => main()->USER_ID));
		if ($is_community) {
			return js_redirect("./?object=community");
		}
		return js_redirect("./?object=".'blog'."&action=show_posts"._add_get(array("page")));
	}

	/**
	* Delete attached image
	*/
	function _delete_attach_image () {
		if (empty(main()->_user_info)) {
			return _error_need_login();
		}
		// Ban check
		if ($_ban_error = module('blog')->_ban_check()) {
			return $_ban_error;
		}
		$_GET["id"] = intval($_GET["id"]);
		// Get post info
		$sql = "SELECT * FROM ".db('blog_posts')." WHERE ";
		if (module('blog')->HIDE_TOTAL_ID) {
			$sql .= " id2=".intval($_GET["id"])." AND user_id=".intval($GLOBALS['HOSTING_ID'] ? $GLOBALS['HOSTING_ID'] : main()->USER_ID);
		} else {
			$sql .= " id=".intval($_GET["id"]);
		}
		// Try to get given user info
		$post_info = db()->query_fetch($sql);
		if (empty($post_info["id"])) {
			return _e("No such post!");
		}
		
		$is_community = $this->_check_community_permissions($post_info["user_id"]);
		if(common()->_error_exists()){
			return _e();
		}
		
		// if this post in community
		$post_info_real_user_id = $is_community ? $post_info["poster_id"] : $post_info["user_id"];
		
		if ($post_info_real_user_id != main()->USER_ID) {
			return _e("Not your post!");
		}

		// Delete image
		$attach_fs_path = "";
		if (!empty($post_info["attach_image"])) {
			$attach_fs_path = module('blog')->_attach_fs_path($post_info);
		}
		if ($attach_fs_path && file_exists($attach_fs_path)) {
			unlink($attach_fs_path);
		}
		db()->query("UPDATE ".db('blog_posts')." SET attach_image='' WHERE id=".intval($post_info["id"])." AND user_id='".intval(main()->USER_ID)."' LIMIT 1");
		db()->update('user', array('last_update' => time()), main()->USER_ID);
		return js_redirect($_SERVER["HTTP_REFERER"], 1);
	}

	/**
	* Load attach image
	*/
	function _load_attach_image ($new_file_name = "") {
		// Create new file name
		if (empty($new_file_name)) {
			$new_file_name = main()->USER_ID. "-". substr(md5(microtime(true)), 0, 8). ".jpg";
		}
		// Placeholder
		$post_info = array(
			"user_id"		=> intval(main()->USER_ID),
			"attach_image"	=> $new_file_name,
		);
		$attach_fs_path = "";
		if (!empty($post_info["attach_image"])) {
			$attach_fs_path = module('blog')->_attach_fs_path($post_info);
		}
		if (!$attach_fs_path) {
			return false;
		}
		// Params
		$LIMIT_X = &module('blog')->ATTACH_LIMIT_X;
		$LIMIT_Y = &module('blog')->ATTACH_LIMIT_Y;
		$MAX_IMAGE_SIZE = &module('blog')->MAX_IMAGE_SIZE;
		// Get attached files dir
		$photo_dir = dirname($attach_fs_path)."/";
		_mkdir_m($photo_dir, module('blog')->DEF_DIR_MODE, 1);
		$photo_path = $photo_dir. $new_file_name;
		// Do upload image
		$upload_result = common()->upload_image($photo_path, "attach_image", $MAX_IMAGE_SIZE);
		if (!$upload_result) {
			return false;
		}
		// Make thumbnail
		$resize_result = common()->make_thumb($photo_path, $photo_path, $LIMIT_X, $LIMIT_Y);
		// Check if file uploaded successfully
		if (!$resize_result || !file_exists($photo_path) || !filesize($photo_path)) {
			if (file_exists($photo_path)) {
				unlink($photo_path);
			}
			return trigger_error("Unable to resize image", E_USER_WARNING);
		}
		return $new_file_name;
	}
	
	function _check_community_permissions($community_user_id){
	
		$user_info = user($community_user_id, array("group"));
		
		//if this post in community
		if($user_info["group"] == "99"){
			$community_info = db()->query_fetch("SELECT * FROM ".db('community')." WHERE user_id=".intval($community_user_id));
			$GLOBALS["community_info"] = $community_info;

			if(empty($community_info)){
				_re("No community");
			}
			
			$community_user_settings = db()->query_fetch("SELECT * FROM ".db('community_users')." WHERE community_id=".intval($community_info["id"])." AND user_id = ".intval(main()->USER_ID));
			$GLOBALS["community_user_settings"] = $community_user_settings;
					
			if(empty($community_user_settings)){
				_re("You not join to this community");
			}
			
			if($community_info["postlevel"] == "select"){
				if(!$community_user_settings["post"]){
					_re("You not allowed post in this community");
				}
			}
			return true;
		}

		return false;
	}
}
