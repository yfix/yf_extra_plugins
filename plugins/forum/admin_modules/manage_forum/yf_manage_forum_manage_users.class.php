<?php

/**
* Manage forum users
* 
* @package		YF
* @author		YFix Team <yfix.dev@gmail.com>
* @version		1.0
*/
class yf_manage_forum_manage_users {

	/**
	* manage users
	*/
	function _manage_users () {
		$sql = 'SELECT * FROM '.db('forum_users').'';
		list($add_sql, $pages, $total) = common()->divide_pages($sql);
		$Q = db()->query($sql. $add_sql);
		while ($A = db()->fetch_assoc($Q)) {
			$forum_users[$A['id']] = $A;
		}
		if (!empty($forum_users)) {
			$users_infos = user(array_keys($forum_users), 'name,nick,login,email,profile_url');
		}
		foreach ((array)$forum_users as $_id => $_info) {
			$user_name = strlen($_info['name']) ? $_info['name'] : _display_name($users_infos[$_id]);
			$group_name = module('manage_forum')->_forum_groups[$_info['group']]['title'];

			$items[$_id] = array(
				'bg_class'		=> $i++ % 2 ? 'bg1' : 'bg2',
				'id'			=> intval($_id),
				'name'			=> _prepare_html($user_name),
				'group_id'		=> intval($_info['group']),
				'group_name'	=> _prepare_html($group_name),
				'num_posts'		=> intval($_info['user_posts']),
				'last_visit'	=> _format_date($_info['last_visit']),
				'edit_link'		=> './?object='.$_GET['object'].'&action=edit_user&id='.intval($_id),
				'delete_link'	=> './?object='.$_GET['object'].'&action=delete_user&id='.intval($_id),
			);
		}
		$replace = array(
			'items'		=> $items,
			'pages'		=> $pages,
			'total'		=> $total,
		);
		$body = tpl()->parse($_GET['object'].'/admin/manage_users_main', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* edit user
	*/
	function _edit_user () {
		$_GET['id'] = intval($_GET['id']);
		if ($_GET['id']) {
			$forum_user_info = db()->query_fetch('SELECT * FROM '.db('forum_users').' WHERE id='.intval($_GET['id']));
		}
		if (!$forum_user_info) {
			return _e('No such user');
		}
		$user_info = user($_GET['id']);
		if (main()->is_post()) {
			if (!isset(module('manage_forum')->_forum_groups[$_POST['group']])) {
				_re('Wrong group');
			}
			if (!common()->_error_exists()) {
				db()->UPDATE('forum_users', array('group' => intval($_POST['group'])), 'id='.intval($_GET['id']));
				return js_redirect('./?object='.$_GET['object'].'&action=manage_users');
			}
		}
		$DATA = $forum_user_info;
		foreach ((array)$_POST as $k => $v) {
			if (isset($DATA[$k])) {
				$DATA[$k] = $v;
			}
		}
		$groups = array();
		foreach ((array)module('manage_forum')->_forum_groups as $_group_id => $_group_info) {
			$groups[$_group_id] = _prepare_html($_group_info['title']);
		}
		$replace = array(
			'is_for_edit'	=> 1,
			'form_action'	=> './?object='.$_GET['object'].'&action='.$_GET['action'].'&id='.$_GET['id'],
			'back_link'		=> './?object='.$_GET['object'].'&action=manage_users',
			'error_message'	=> _e(),
			'user_id'		=> intval($_GET['id']),
			'user_name'		=> _prepare_html(strlen($forum_user_info['name']) ? $forum_user_info['name'] : _display_name($user_info)),
			'group_box'		=> common()->select_box('group', $groups, $DATA['group'], false, 2, '', false),
		);
		$body = tpl()->parse($_GET['object'].'/admin/edit_user_form', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* manage group
	*/
	function _manage_groups () {
		$Q = db()->query('SELECT `group`, COUNT(*) AS num FROM '.db('forum_users').' GROUP BY `group`');
		while ($A = db()->fetch_assoc($Q)) {
			$users_by_groups[$A['group']] = $A['num'];
		}
		foreach ((array)module('manage_forum')->_forum_groups as $_group_id => $_group_info) {
			$replace2 = array(
				'bg_class'		=> $i++ % 2 ? 'bg1' : 'bg2',
				'id'			=> intval($_group_id),
				'title'			=> _prepare_html($_group_info['title']),
				'is_admin'		=> intval((bool)$_group_info['is_admin']),
				'is_mod'		=> intval((bool)$_group_info['is_moderator']),
				'num_users'		=> intval($users_by_groups[$_group_id]),
				'edit_link'		=> './?object='.$_GET['object'].'&action=edit_group&id='.intval($_group_id),
				'delete_link'	=> './?object='.$_GET['object'].'&action=delete_group&id='.intval($_group_id),
				'clone_link'	=> './?object='.$_GET['object'].'&action=clone_group&id='.intval($_group_id),
			);
			$items .= tpl()->parse($_GET['object'].'/admin/manage_groups_item', $replace2);
		}
		$replace = array(
			'items'		=> $items,
			'total'		=> count(module('manage_forum')->_forum_groups),
			'add_link'	=> './?object='.$_GET['object'].'&action=add_group',
		);
		$body = tpl()->parse($_GET['object'].'/admin/manage_groups_main', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* edit group
	*/
	function _edit_group () {
		$_GET['id'] = intval($_GET['id']);
		if (!isset(module('manage_forum')->_forum_groups[$_GET['id']])) {
			return _e('No such group');
		}
		$group_info = module('manage_forum')->_forum_groups[$_GET['id']];
		if (main()->is_post()) {
			if (empty($_POST['title'])) {
				_re('Title is required');
			}
			if (!common()->_error_exists()) {
				$sql_array['title'] = $_POST['title'];
				foreach ((array)module('manage_forum')->_group_triggers as $_name => $_desc) {
					if ($_POST[$_name] == $group_info[$_name]) {
						continue;
					}
					$sql_array[$_name] = $_POST[$_name];
				}
				db()->UPDATE('forum_groups', $sql_array, 'id='.intval($_GET['id']));
				cache_del('forum_groups');
				return js_redirect('./?object='.$_GET['object'].'&action=manage_groups');
			}
		}
		$DATA = $group_info;
		foreach ((array)$_POST as $k => $v) {
			if (isset($DATA[$k])) {
				$DATA[$k] = $v;
			}
		}
		foreach ((array)module('manage_forum')->_group_triggers as $_name => $_desc) {
			$group_triggers[$_name] = array(
				'desc'	=> _prepare_html($_desc),
				'box'	=> common()->radio_box($_name, module('manage_forum')->_std_trigger, $DATA[$_name], false, 2, '', false),
			);
		}
		$replace = array(
			'is_for_edit'	=> 1,
			'form_action'	=> './?object='.$_GET['object'].'&action='.$_GET['action'].'&id='.$_GET['id'],
			'back_link'		=> './?object='.$_GET['object'].'&action=manage_groups',
			'error_message'	=> _e(),
			'title'			=> _prepare_html($DATA['title']),
			'group_triggers'=> $group_triggers,
		);
		$body = tpl()->parse($_GET['object'].'/admin/edit_group_form', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* add group
	*/
	function _add_group () {
		if (main()->is_post()) {
			if (empty($_POST['title'])) {
				_re('Title is required');
			}
			if (!common()->_error_exists()) {
				$sql_array['title'] = $_POST['title'];
				foreach ((array)module('manage_forum')->_group_triggers as $_name => $_desc) {
					if ($_POST[$_name] == $group_info[$_name]) {
						continue;
					}
					$sql_array[$_name] = $_POST[$_name];
				}
				db()->INSERT('forum_groups', $sql_array);
				cache_del('forum_groups');
				return js_redirect('./?object='.$_GET['object'].'&action=manage_groups');
			}
		}
		$DATA = $_POST;
		foreach ((array)module('manage_forum')->_group_triggers as $_name => $_desc) {
			$group_triggers[$_name] = array(
				'desc'	=> _prepare_html($_desc),
				'box'	=> common()->radio_box($_name, module('manage_forum')->_std_trigger, $DATA[$_name], false, 2, '', false),
			);
		}
		$replace = array(
			'is_for_edit'	=> 0,
			'form_action'	=> './?object='.$_GET['object'].'&action='.$_GET['action'],
			'back_link'		=> './?object='.$_GET['object'].'&action=manage_groups',
			'error_message'	=> _e(),
			'title'			=> _prepare_html($DATA['title']),
			'group_triggers'=> $group_triggers,
		);
		$body = tpl()->parse($_GET['object'].'/admin/edit_group_form', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* delete group
	*/
	function _delete_group () {
		if (!isset(module('manage_forum')->_forum_groups[$_GET['id']])) {
			return _e('No such group');
		}
		db()->query('DELETE FROM '.db('forum_groups').' WHERE id='.intval($_GET['id']));
		cache_del('forum_groups');
		return js_redirect('./?object='.$_GET['object'].'&action=manage_groups');
	}

	/**
	* Clone group
	*/
	function _clone_group () {
		if (!isset(module('manage_forum')->_forum_groups[$_GET['id']])) {
			return _e('No such group');
		}
		$group_info = module('manage_forum')->_forum_groups[$_GET['id']];
		$sql = $group_info;
		unset($sql['id']);
		$sql['title'] = $sql['title'].'_clone';
		db()->INSERT('forum_groups', $sql);
		$NEW_GROUP_ID = db()->INSERT_ID();

		cache_del('forum_groups');
		return js_redirect('./?object='.$_GET['object'].'&action=manage_groups');
	}

	/**
	* manage moderators
	*/
	function _manage_moderators () {
		foreach ((array)module('manage_forum')->_forum_moderators as $_mod_id => $_mod_info) {
			$forums_array = array();
			foreach (explode(',', $_mod_info['forums_list']) as $_forum_id) {
				$forums_array[$_forum_id] = array(
					'name'	=> _prepare_html(module('manage_forum')->_forums_array[$_forum_id]['name']),
					'link'	=> './?object='.$_GET['object'].'&action=view_forum&id='.intval($_forum_id),
				);
			}
			$replace2 = array(
				'bg_class'		=> $i++ % 2 ? 'bg1' : 'bg2',
				'user_id'		=> intval($_mod_id),
				'user_name'		=> _prepare_html($_mod_info['member_name']),
				'profile_link'	=> module('manage_forum')->_user_profile_link(array('user_id' => $_mod_info['member_id'], 'user_name' => $_mod_info['member_name'])),
				'forums_array'	=> $forums_array,
				'edit_link'		=> './?object='.$_GET['object'].'&action=edit_moderator&id='.intval($_mod_id),
				'delete_link'	=> './?object='.$_GET['object'].'&action=delete_moderator&id='.intval($_mod_id),
			);
			$items .= tpl()->parse($_GET['object'].'/admin/manage_mods_item', $replace2);
		}
		$replace = array(
			'items'		=> $items,
			'total'		=> count(module('manage_forum')->_forum_moderators),
			'add_link'	=> './?object='.$_GET['object'].'&action=add_moderator',
		);
		$body = tpl()->parse($_GET['object'].'/admin/manage_mods_main', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* edit moderator
	*/
	function _edit_moderator () {
		$_GET['id'] = intval($_GET['id']);
		if (!isset(module('manage_forum')->_forum_groups[$_GET['id']])) {
			return _e('No such moderator');
		}
		$mod_info = module('manage_forum')->_forum_moderators[$_GET['id']];
		if (main()->is_post()) {
			if (!empty($_POST['forums_list'])) {
				$_tmp_forums_list = array();
				$_tmp_array = is_array($_POST['forums_list']) ? $_POST['forums_list'] : explode(',', $_POST['forums_list']);
				foreach ((array)$_tmp_array as $_forum_id) {
					$_forum_id = intval($_forum_id);
					if (empty($_forum_id) || !isset(module('manage_forum')->_forums_array[$_forum_id])) {
						continue;
					}
					$_tmp_forums_list[$_forum_id] = $_forum_id;
				}
				$_POST['forums_list'] = implode(',', $_tmp_forums_list);
			}
			if (empty($_POST['forums_list'])) {
				_re('Forums list is required');
			}
			if (!common()->_error_exists()) {
				$sql_array['forums_list'] = $_POST['forums_list'];
				foreach ((array)module('manage_forum')->_moderator_triggers as $_name => $_desc) {
					if ($_POST[$_name] == $mod_info[$_name]) {
						continue;
					}
					$sql_array[$_name] = $_POST[$_name];
				}
				db()->UPDATE('forum_moderators', $sql_array, 'id='.intval($_GET['id']));

				$forum_user_info = db()->query_fetch('SELECT * FROM '.db('forum_users').' WHERE id='.intval($mod_info['member_id']));
				if (empty($forum_user_info)) {
					$forum_user_info = array(
						'id'			=> intval($mod_info['member_id']),
						'name'	 		=> _es($mod_info['member_name']),
						'group'	 		=> 3, // Member
						'user_regdate'	=> time(),
						'active' 		=> 1,
					);
					db()->INSERT('forum_users', $forum_user_info);
				}
				if ($forum_user_info['group'] > 2) {
					db()->UPDATE('forum_users', array('group' => 2), 'id='.intval($mod_info['member_id']));
				}
				cache_del('forum_moderators');
				return js_redirect('./?object='.$_GET['object'].'&action=manage_moderators');
			}
		}
		$DATA = $mod_info;
		foreach ((array)$_POST as $k => $v) {
			if (isset($DATA[$k])) {
				$DATA[$k] = $v;
			}
		}
		foreach ((array)module('manage_forum')->_moderator_triggers as $_name => $_desc) {
			$moderator_triggers[$_name] = array(
				'desc'	=> _prepare_html($_desc),
				'box'	=> common()->radio_box($_name, module('manage_forum')->_std_trigger, $DATA[$_name], false, 2, '', false),
			);
		}
		$_forums_for_box = $this->_prepare_forums_for_mods_select();
		$selected_forums = array();
		foreach ((array)explode(',', $DATA['forums_list']) as $_forum_id) {
			$selected_forums[$_forum_id] = $_forum_id;
		}
		$replace = array(
			'is_for_edit'		=> 1,
			'form_action'		=> './?object='.$_GET['object'].'&action='.$_GET['action'].'&id='.$_GET['id'],
			'back_link'			=> './?object='.$_GET['object'].'&action=manage_moderators',
			'error_message'		=> _e(),
			'user_id'			=> intval($DATA['member_id']),
			'profile_link'		=> module('manage_forum')->_user_profile_link(array('user_id' => $DATA['member_id'], 'user_name' => $DATA['member_name'])),
			'forums_list'		=> $DATA['forums_list'],
			'forums_box'		=> common()->multi_select('forums_list', $_forums_for_box, $selected_forums, false, 2, ' size=7 class=small_for_select ', false),
			'moderator_triggers'=> $moderator_triggers,
		);
		$body = tpl()->parse($_GET['object'].'/admin/edit_moderator_form', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/** 
	* helper for forums select box for add/edit moderators
	*/
	function _prepare_forums_for_mods_select () {
		$cats = array();
		foreach ((array)module('manage_forum')->_forum_cats_array as $_cat_id => $_cat_info) {
			$_cat_name = $_cat_info['name'];
			$forums = array();
			foreach ((array)module('manage_forum')->_prepare_forums_for_select($skip_id, $_cat_id) as $k => $v) {
				$forums[$k] = $v;
			}
			$cats['######## '. $_cat_name] = $forums;
		}
		return $cats;
	}

	/**
	* add moderator
	*/
	function _add_moderator () {
		$def_mod_rights = array();
		foreach ((array)module('manage_forum')->_forum_groups as $_group_id => $_group_info) {
			if ($_group_info['is_moderator']) {
				$MOD_GROUP = $_group_id;
				break;
			}
		}
		foreach ((array)module('manage_forum')->_forum_groups[$MOD_GROUP] as $_name => $_value) {
			if (!isset(module('manage_forum')->_moderator_triggers[$_name])) {
				continue;
			}
			$def_mod_rights[$_name] = $_value;
		}
		if (main()->is_post()) {
			if (empty($_POST['user_id'])) {
				_re('User ID is required');
			} else {
				$member_info = db()->query_fetch('SELECT * FROM '.db('user').' WHERE id='.intval($_POST['user_id']));
				if (empty($member_info)) {
					_re('No user with such ID');
				}
			}
			if (!empty($_POST['forums_list'])) {
				$_tmp_forums_list = array();
				$_tmp_array = is_array($_POST['forums_list']) ? $_POST['forums_list'] : explode(',', $_POST['forums_list']);
				foreach ((array)$_tmp_array as $_forum_id) {
					$_forum_id = intval($_forum_id);
					if (empty($_forum_id) || !isset(module('manage_forum')->_forums_array[$_forum_id])) {
						continue;
					}
					$_tmp_forums_list[$_forum_id] = $_forum_id;
				}
				$_POST['forums_list'] = implode(',', $_tmp_forums_list);
			}
			if (empty($_POST['forums_list'])) {
				_re('Forums list is required');
			}
			if (!common()->_error_exists()) {
				$sql_array['forums_list']	= $_POST['forums_list'];
				$sql_array['member_id']		= $_POST['user_id'];
				$sql_array['member_name']	= _display_name($member_info);
				foreach ((array)module('manage_forum')->_moderator_triggers as $_name => $_desc) {
					if ($_POST[$_name] == $mod_info[$_name]) {
						continue;
					}
					$sql_array[$_name] = $_POST[$_name];
				}
				db()->INSERT('forum_moderators', $sql_array);
				$mod_info = $sql_array;

				$forum_user_info = db()->query_fetch('SELECT * FROM '.db('forum_users').' WHERE id='.intval($mod_info['member_id']));
				if (empty($forum_user_info)) {
					$forum_user_info = array(
						'id'			=> intval($mod_info['member_id']),
						'name'	 		=> _es($mod_info['member_name']),
						'group'	 		=> 3, // Member
						'user_regdate'	=> time(),
						'active' 		=> 1,
					);
					db()->INSERT('forum_users', $forum_user_info);
				}
				if ($forum_user_info['group'] > 2) {
					db()->UPDATE('forum_users', array('group' => 2), 'id='.intval($mod_info['member_id']));
				}
				cache_del('forum_moderators');
				return js_redirect('./?object='.$_GET['object'].'&action=manage_moderators');
			}
		}
		$DATA = $def_mod_rights;
		foreach ((array)$_POST as $k => $v) {
			if (isset($DATA[$k])) {
				$DATA[$k] = $v;
			}
		}
		foreach ((array)module('manage_forum')->_moderator_triggers as $_name => $_desc) {
			$moderator_triggers[$_name] = array(
				'desc'	=> _prepare_html($_desc),
				'box'	=> common()->radio_box($_name, module('manage_forum')->_std_trigger, $DATA[$_name], false, 2, '', false),
			);
		}
		$_forums_for_box = $this->_prepare_forums_for_mods_select();
		$selected_forums = array();
		foreach ((array)explode(',', $DATA['forums_list']) as $_forum_id) {
			$selected_forums[$_forum_id] = $_forum_id;
		}
		$replace = array(
			'is_for_edit'		=> 0,
			'form_action'		=> './?object='.$_GET['object'].'&action='.$_GET['action'],
			'back_link'			=> './?object='.$_GET['object'].'&action=manage_moderators',
			'error_message'		=> _e(),
			'user_id'			=> intval($DATA['member_id']),
			'forums_list'		=> $DATA['forums_list'],
			'forums_box'		=> common()->multi_select('forums_list', $_forums_for_box, $selected_forums, false, 2, ' size=7 class=small_for_select ', false),
			'moderator_triggers'=> $moderator_triggers,
		);
		$body = tpl()->parse($_GET['object'].'/admin/edit_moderator_form', $replace);
		return module('manage_forum')->_show_main_tpl($body);
	}

	/**
	* delete moderator
	*/
	function _delete_moderator () {
		if (!isset(module('manage_forum')->_forum_moderators[$_GET['id']])) {
			return _e('No such moderator');
		}
		db()->query('DELETE FROM '.db('forum_moderators').' WHERE id='.intval($_GET['id']));
		cache_del('forum_moderators');
		return js_redirect('./?object='.$_GET['object'].'&action=manage_moderators');
	}
}
