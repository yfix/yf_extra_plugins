<?php

/**
* Gallery tags
* 
* @package		YF
* @author		YFix Team <yfix.dev@gmail.com>
* @version		1.0
*/
class yf_gallery_tags {

	/** @var string @conf_skip
	*	Allow here only these below \x7F == 127 (ASCII) :
	*		\x0A == 13 (CR), 
	*		\x20 == 32 (Space), 
	*		\x30-\x39 (0-9), 
	*		\x41-\x5A (A-Z),
	*		\x61-\x7A (a-z)
	*/
	public $REGEXP_ALLOWED		= '/[\x00-\x09\x0B-\x1F\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F]/ims';
	/** @var int */
	public $MIN_KEYWORD_LENGTH	= 3;
	/** @var int */
	public $MAX_KEYWORD_LENGTH	= 30;
	/** @var bool */
	public $UTF8_MODE			= 0;

	/**
	* Manage tags for the selected photo
	*/
	function _edit_tags ($photo_id = 0) {
		if (MAIN_TYPE_USER && (!module('gallery')->ALLOW_TAGGING || !main()->USER_ID)) {
			return "";
		}
		if (empty($photo_id)) {
			$photo_id = intval($_GET["id"]);
		}
		if (empty($photo_id)) {
			return "";
		}
		$_tags = array();
		$Q = db()->query("SELECT * FROM ".db('tags')." WHERE object_name='gallery' AND object_id=".intval($photo_id));
		while ($A = db()->fetch_assoc($Q)) {
			$_tags[$A["id"]] = $A["text"];
		}
		if (empty(module('gallery')->_photo_info)) {
			module('gallery')->_photo_info = db()->query_fetch("SELECT * FROM ".db('gallery_photos')." WHERE id=".intval($photo_id));
			$photo_info = &module('gallery')->_photo_info;
		}
		if (MAIN_TYPE_USER) {
			module('gallery')->is_own_gallery = intval(!empty(main()->USER_ID) && main()->USER_ID == module('gallery')->_photo_info["user_id"]);
		} elseif (MAIN_TYPE_ADMIN) {
			module('gallery')->is_own_gallery = true;
		}
		if (!module('gallery')->is_own_gallery && count($_tags) >= module('gallery')->TAGS_PER_PHOTO) {
			return "";
		}
		$tags_to_edit = implode("\r\n", (array)$_tags);
		if (main()->is_post()) {
			$keywords_array = array();

			$source = $_POST["tags"];
			$source = str_replace(array("\r", "\t"), array("", " "), $source);
			$source = trim(preg_replace("/[ ]{2,}/ims", " ", $source));
			$source = trim(preg_replace("/[\n]{2,}/ims", "\n", $source));
			$source = trim(preg_replace($this->REGEXP_ALLOWED, "", $source));
			// Split by lines
			$lines	= explode("\n", $source);
//			if (count($lines) > module('gallery')->TAGS_PER_PHOTO) {
//				_re("Too many keywords (".count($lines)."), allowed max=".module('gallery')->TAGS_PER_PHOTO);
//			}
			// Last cleanup
			foreach ((array)$lines as $cur_word) {
				if (empty($cur_word) || (strlen($cur_word) * ($this->UTF8_MODE ? 2 : 1)) < $this->MIN_KEYWORD_LENGTH) {
//					_re("Keyword \""._prepare_html($cur_word)."\" is too short (min length=".$this->MIN_KEYWORD_LENGTH.")");
					continue;
				}
				// Check max number of keywords
				if (++$i > module('gallery')->TAGS_PER_PHOTO) {
					break;
				}
				// Cut long keywords
				if ($this->MAX_KEYWORD_LENGTH && strlen($cur_word) > $this->MAX_KEYWORD_LENGTH * ($this->UTF8_MODE ? 2 : 1)) {
//					_re("Keyword \""._prepare_html($cur_word)."\" is too long (max length=".$this->MAX_KEYWORD_LENGTH.")");
					$cur_word = substr($cur_word, 0, $this->MAX_KEYWORD_LENGTH);
				}
				if (!isset($keywords_array[$cur_word])) {
					$keywords_array[$cur_word] = $cur_word;
				}
			}
			$TAGS_CHANGED = true;
			// Check if we have non-changed content
			if (count($_tags) == count($keywords_array) && !array_diff($_tags, $keywords_array)) {
				$TAGS_CHANGED = false;
			}
			if ($TAGS_CHANGED) {
				$_new_tags = $_tags;
				// Find and remove from saving not changed tags
				foreach (array_intersect((array)$_new_tags, (array)$keywords_array) as $_key => $_val) {
					unset($keywords_array[$_val]);
					unset($_new_tags[$_key]);
				}
				// Delete is only for owner
				if (module('gallery')->is_own_gallery) {
					$ids_to_delete = array_keys((array)$_new_tags);
					// Delete old keywords
					if (!empty($ids_to_delete)) {
						db()->query("DELETE FROM ".db('tags')." WHERE object_name='gallery' AND object_id=".intval($photo_id)." AND id IN(".implode(",", $ids_to_delete).")");
					}
				}
				$num_tags = count($_tags);
				// Save new keywords
				foreach ((array)$keywords_array as $_word) {
					if (!module('gallery')->is_own_gallery && $num_tags >= module('gallery')->TAGS_PER_PHOTO) {
						break;
					}
					$num_tags++;
					db()->INSERT("tags", array(
						"object_name"	=> "gallery",
						"object_id"		=> intval($photo_id),
						"user_id"		=> main()->USER_ID,
						"text"			=> _es($_word),
						"add_date"		=> time(),
						"active"		=> 1,
					));
					$TAG_ID = db()->INSERT_ID();
					db()->INSERT("log_tags", array(
						"object_id"		=> intval($photo_id),
						"object_name"	=> "gallery",
						"tag_id"		=> intval($TAG_ID),
						"text"			=> _es($_word),
						"date"			=> time(),
						"site_id"		=> (int)conf('SITE_ID'),
						"owner_id"		=> intval(module('gallery')->_photo_info["user_id"]),
						"user_id"		=> intval($_SESSION[MAIN_TYPE_ADMIN ? "admin_id" : "user_id"]),
					//	"user_group"	=> intval($_SESSION[MAIN_TYPE_ADMIN ? "admin_group" : "user_group"]),
					//	"is_admin"		=> MAIN_TYPE_ADMIN ? 1 : 0,
						"ip"			=> _es(common()->get_ip()),
						"user_agent"	=> _es($_SERVER["HTTP_USER_AGENT"]),
						"referer"		=> _es($_SERVER["HTTP_REFERER"]),
						"request_uri"	=> _es($_SERVER["REQUEST_URI"]),
					));
				}
			}
// TODO: move into template
			return "<div align='center'>Saved successfully<br /> <a href='javascript:self.window.close();'>Close window</a></div>";
//			echo js_redirect("./?object=".'gallery'."&action=".$_GET["action"]. ($_GET["id"] ? "&id=".$_GET["id"] : ""));
		}
		// Prepare tags array
		if (main()->NO_GRAPHICS) {
			$tags = array();
			$_prefetched_tags = module('gallery')->_get_tags($photo_id);
			foreach ((array)$GLOBALS['_gallery_tags'][$photo_id] as $_name) {
				$tags[$_name] = process_url("./?object=".'gallery'."&action=tag&id=".urlencode($_name));
			}
		}
		// Prepare template
		$replace = array(
			"form_action"	=> "./?object=".'gallery'."&action=".$_GET["action"]. ($_GET["id"] ? "&id=".$_GET["id"] : ""),
			"tags_to_edit"	=> $tags_to_edit,
			"max_num_tags"	=> intval(module('gallery')->TAGS_PER_PHOTO),
			"tags"			=> !empty($tags) ? $tags : "",
		);
		return tpl()->parse('gallery'."/edit_tags_form", $replace);
	}

	/**
	* Prefetch tags for given ids
	*/
	function _get_tags ($photos_ids = array()) {
		if (empty($photos_ids)) {
			return false;
		}
		// Convert single number to array
		if (is_numeric($photos_ids)) {
			$photos_ids = array($photos_ids);
		}
		$output = array();
		// Check if it is cached already
		foreach ((array)$photos_ids as $_photo_id) {
	  		if (isset($GLOBALS['_gallery_tags'][$_photo_id])) {
				$tags[$_photo_id] = $GLOBALS['_gallery_tags'][$_photo_id];
				unset($photos_ids);
			}
		}
		// Get data from db
		if (!empty($photos_ids)) {
			$Q = db()->query("SELECT * FROM ".db('tags')." WHERE object_name='gallery' AND object_id IN(".implode(",", $photos_ids).")");
			while ($A = db()->fetch_assoc($Q)) {
				$tags[$A["object_id"]][$A["text"]] = $A["text"];
				$GLOBALS['_gallery_tags'][$A["object_id"]][$A["text"]] = $A["text"];
			}
		}
		return $tags;
	}
	
	/**
	* Display photos filtered by tag
	*/
	function _show_by_tag() {
		if (!module('gallery')->ALLOW_TAGGING) {
			return _e("Tagging disabled");
		}
		$_POST["tag"] = trim(preg_replace($this->REGEXP_ALLOWED, "", $_GET["id"]));
		module('gallery')->_SEARCH_AS_PHOTOS = $_POST["as_photos"] = 1;

		module('gallery')->clear_filter(1);
		module('gallery')->save_filter(1);

		return _class_safe('gallery_stats', 'modules/gallery/')->_show_all_galleries(array("pager_url" => "./?object=".'gallery'."&action=tag&id=".urlencode($_POST["tag"])));
	}
}
