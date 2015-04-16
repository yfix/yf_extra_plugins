<?php

/**
* Inline administration methods
* 
* @package		YF
* @author		YFix Team <yfix.dev@gmail.com>
* @version		1.0
*/
class yf_forum_admin {

	/**
	*/
	function _init () {
		$this->SYNC_OBJ = _class('forum_sync', 'modules/forum/');
		if (FORUM_IS_MODERATOR) {
			module('forum')->_apply_moderator_rights();
		}
	}
	
	/**
	*/
	function _show_main() {
		if (!FORUM_IS_ADMIN && !FORUM_IS_MODERATOR) {
			return js_redirect('./?object=forum');
		}
		$_GET['id'] = intval($_GET['id']);
		if (!empty($_POST['t_act'])) {
			if (in_array($_POST['t_act'], array('open','close'))) {
				$body = $this->_topic_open_close();
			} elseif (in_array($_POST['t_act'], array('pin','unpin'))) {
				$body = $this->_topic_pin_unpin();
			} elseif (in_array($_POST['t_act'], array('approve','unapprove'))) {
				$body = $this->_topic_approve_unapprove();
			} elseif (in_array($_POST['t_act'], array('move', 'do_move'))) {
				$body = $this->_topic_move();
			} elseif (in_array($_POST['t_act'], array('merge'))) {
				$body = $this->_topic_merge();
			} elseif (in_array($_POST['t_act'], array('delete'))) {
				$body = $this->_topic_delete();
			}
		}
		if (!empty($_POST['p_act'])) {
			if (in_array($_POST['p_act'], array('split'))) {
				$body = $this->_posts_split();
			} elseif (in_array($_POST['p_act'], array('approve','unapprove'))) {
				$body = $this->_posts_approve_unapprove();
			} elseif (in_array($_POST['p_act'], array('move', 'do_move'))) {
				$body = $this->_posts_move();
			} elseif (in_array($_POST['p_act'], array('merge'))) {
				$body = $this->_posts_merge();
			} elseif (in_array($_POST['p_act'], array('delete'))) {
				$body = $this->_posts_delete();
			}
		}
		return module('forum')->_show_main_tpl($body);
	}
	
	/**
	*/
	function _topic_open_close() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		$forum_info = module('forum')->_forums_array[$_GET['id']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (($_POST['t_act'] == 'open' && !module('forum')->USER_RIGHTS['open_topics']) 
			|| ($_POST['t_act'] == 'close' && !module('forum')->USER_RIGHTS['close_topics'])
			|| (FORUM_IS_MODERATOR && !module('forum')->_moderate_forum_allowed($forum_info['id']))
		) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			db()->update_safe('forum_topics', array(
				'status' => $_POST['t_act'] == 'open' ? 'a' : 'c'
			), 'forum='.intval($forum_info['id']).' AND id IN('.implode(',', $selected_ids).')');
		}
		return js_redirect(module('forum')->_link_to_forum($_GET['id']));
	}
	
	/**
	*/
	function _topic_pin_unpin() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		$forum_info = module('forum')->_forums_array[$_GET['id']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (($_POST['t_act'] == 'pin' && !module('forum')->USER_RIGHTS['pin_topics']) 
			|| ($_POST['t_act'] == 'unpin' && !module('forum')->USER_RIGHTS['unpin_topics'])
			|| (FORUM_IS_MODERATOR && !module('forum')->_moderate_forum_allowed($forum_info['id']))
		) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			$new_pinned = $_POST['t_act'] == 'pin' ? 1 : 0;
			db()->query('UPDATE '.db('forum_topics').' SET pinned='.$new_pinned.' WHERE forum='.intval($forum_info['id']).' AND id IN('.implode(',', $selected_ids).')');
		}
		return js_redirect(module('forum')->_link_to_forum($_GET['id']));
	}
	
	/**
	*/
	function _topic_approve_unapprove() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		$forum_info = module('forum')->_forums_array[$_GET['id']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (($_POST['t_act'] == 'approve' && !module('forum')->USER_RIGHTS['approve_topics']) 
			|| ($_POST['t_act'] == 'unapprove' && !module('forum')->USER_RIGHTS['unapprove_topics'])
			|| (FORUM_IS_MODERATOR && !module('forum')->_moderate_forum_allowed($forum_info['id']))
		) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			$new_approve		= $_POST['t_act'] == 'approve' ? 1 : 0;
			$new_posts_status	= $_POST['t_act'] == 'approve' ? 'a' : 'u';
			db()->query('UPDATE '.db('forum_topics').' SET approved='.$new_approve.' WHERE forum='.intval($forum_info['id']).' AND id IN('.implode(',', $selected_ids).')');
			db()->query('UPDATE '.db('forum_posts').' SET status="'.$new_posts_status.'" WHERE topic IN('.implode(',', $selected_ids).')');
			if (is_object($this->SYNC_OBJ)) {
				$this->SYNC_OBJ->_update_forum_record($forum_info['id']);
				$this->SYNC_OBJ->_fix_subforums();
			}
		}
		return js_redirect(module('forum')->_link_to_forum($_GET['id']));
	}
	
	/**
	*/
	function _topic_delete($SILENT_MODE = false, $_force_topic_id = array()) {
		$FORUM_ID = intval($_GET['id']);
		if (!empty($_force_topic_id)) {
			$force_topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.intval($_force_topic_id));
		}
		if ($force_topic_info['forum']) {
			$FORUM_ID = intval($force_topic_info['forum']);
		}
		$forum_info = module('forum')->_forums_array[$FORUM_ID];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
		} elseif (!empty($force_topic_info)) {
			$selected_ids = array($force_topic_info['id']);
		}
		if (FORUM_IS_MODERATOR && !empty($selected_ids)) {
			$ACCESS_DENIED = false;
			if (!module('forum')->_moderate_forum_allowed($forum_info['id'])) {
				$ACCESS_DENIED = true;
			}
			// Check if any of selected topics is denied to delete by current user
			if (!$ACCESS_DENIED) {
				$Q = db()->query('SELECT * FROM '.db('forum_topics').' WHERE id IN('.implode(',', $selected_ids).')');
				while ($topic_info = db()->fetch_assoc($Q)) {
					if ((FORUM_USER_ID == $topic_info['user_id'] && !module('forum')->USER_RIGHTS['delete_own_topics'])
						|| (FORUM_USER_ID != $topic_info['user_id'] && !module('forum')->USER_RIGHTS['delete_other_topics'])
					) {
						$ACCESS_DENIED = true;
						break;
					}
				}
			}
			if ($ACCESS_DENIED) {
				return module('forum')->_show_error('You are not allowed to perform this action');
			}
		}
		if (!empty($selected_ids)) {
			db()->query('DELETE FROM '.db('forum_topics').' WHERE forum='.intval($forum_info['id']).' AND id IN('.implode(',', $selected_ids).')');
			db()->query('DELETE FROM '.db('forum_posts').' WHERE topic IN('.implode(',', $selected_ids).')');
			if (is_object($this->SYNC_OBJ)) {
				$this->SYNC_OBJ->_update_forum_record($forum_info['id']);
				$this->SYNC_OBJ->_fix_subforums();
			}
		}
		return !$SILENT_MODE ? js_redirect(module('forum')->_link_to_forum($_GET['id'])) : '';
	}
	
	/**
	*/
	function _topic_move() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		if (!module('forum')->USER_RIGHTS['move_topics']) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		$forum_info = module('forum')->_forums_array[$_GET['id']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (FORUM_IS_MODERATOR && !module('forum')->_moderate_forum_allowed($forum_info['id'])) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		if (empty($_POST['new_forum_id']) && !empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			$Q = db()->query('SELECT id,name FROM '.db('forum_topics').' WHERE id IN('.implode(',', $selected_ids).')');
			while ($A = db()->fetch_assoc($Q)) $topic_names[$A['id']] = $A['name'];
			foreach ((array)$selected_ids as $topic_id) {
				$selected_topics[$topic_id] = array(
					'topic_id'		=> $topic_id,
					'topic_name'	=> $topic_names[$topic_id],
				);
			}
			$replace = array(
				'form_action'		=> './?object=forum&action='.$_GET['action'].'&id='.$_GET['id']._add_get(array('page')),
				'forum_id'			=> intval($_GET['id']),
				'forum_name'		=> $forum_info['name'],
				'forums_box'		=> $this->_forums_box(),
				'leave_link_box'	=> common()->radio_box('leave_link', array('No','Yes'), 1),
				'selected_topics'	=> $selected_topics,
			);
			return tpl()->parse('forum'.'/admin/move_topic_main', $replace);
		} else {
			$leave_link		= intval($_POST['leave_link']);
			$old_forum_id	= $_GET['id'];
			$new_forum_id	= intval($_POST['new_forum_id']);
			if ($new_forum_id != $old_forum_id) {
				foreach ((array)$_POST as $k => $v) {
					if (substr($k, 0, 4) == 'tid_' && $v == 1) {
						$selected_ids[] = intval(substr($k, 4));
					}
				}
				$Q = db()->query('SELECT * FROM '.db('forum_topics').' WHERE id IN('.implode(',', $selected_ids).')');
				while ($A = db()->fetch_assoc($Q)) $topics_array[$A['id']] = $A;
				foreach ((array)$topics_array as $topic_id => $topic_info) {
					db()->INSERT('forum_topics', array(
						'forum'				=> intval($new_forum_id),
						'name'				=> _es($topic_info['name']),
						'desc'				=> _es($topic_info['desc']),
						'user_id'			=> intval($topic_info['user_id']),
						'user_name'			=> _es($topic_info['user_name']),
						'created'			=> intval($topic_info['created']),
						'status'			=> _es($topic_info['status']),
						'num_views'			=> intval($topic_info['num_views']),
						'num_posts'			=> intval($topic_info['num_posts']),
						'first_post_id'		=> intval($topic_info['first_post_id']),
						'last_post_id'		=> intval($topic_info['last_post_id']),
						'last_poster_id'	=> intval($topic_info['last_poster_id']),
						'last_poster_name'	=> _es($topic_info['last_poster_name']),
						'last_post_date'	=> intval($topic_info['last_post_date']),
						'icon_id'			=> intval($topic_info['icon_id']),
						'pinned'			=> intval($topic_info['pinned']),
						'approved'			=> intval($topic_info['approved']),
					));
					$new_topics[$topic_id] = intval(db()->insert_id());
				}
				if (!empty($new_topics)) {
					foreach ((array)$new_topics as $old_topic_id => $new_topic_id) {
						db()->query('UPDATE '.db('forum_posts').' SET topic='.intval($new_topic_id).' WHERE topic='.intval($old_topic_id));
					}
					if ($leave_link) {
						foreach ((array)$new_topics as $old_topic_id => $new_topic_id) {
							db()->query('UPDATE '.db('forum_topics').' SET moved_to="'.intval($new_forum_id).','.intval($new_topic_id).'" WHERE id='.intval($old_topic_id));
						}
					} else {
						db()->query('DELETE FROM '.db('forum_topics').' WHERE id IN('.implode(',', $selected_ids).')');
					}
					if (is_object($this->SYNC_OBJ)) {
						$this->SYNC_OBJ->_update_forum_record($old_forum_id);
						$this->SYNC_OBJ->_update_forum_record($new_forum_id);
						$this->SYNC_OBJ->_fix_subforums();
					}
				}
			}
		}
		return js_redirect(module('forum')->_link_to_forum($_GET['id']));
	}
	
	/**
	*/
	function _topic_merge() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		if (!module('forum')->USER_RIGHTS['split_merge']) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		$forum_info = module('forum')->_forums_array[$_GET['id']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (FORUM_IS_MODERATOR && !module('forum')->_moderate_forum_allowed($forum_info['id'])) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			sort($selected_ids);
			if (count($selected_ids) >= 2) {
				$min_id = intval(array_shift($selected_ids));
			}
			if (!empty($min_id) && is_array($selected_ids)) {
				db()->query('UPDATE '.db('forum_posts').' SET topic='.intval($min_id).' WHERE topic IN('.implode(',', $selected_ids).')');
				db()->query('DELETE FROM '.db('forum_topics').' WHERE id IN('.implode(',', $selected_ids).')');
				if (is_object($this->SYNC_OBJ)) {
					$this->SYNC_OBJ->_update_forum_record($forum_info['id']);
					$this->SYNC_OBJ->_fix_subforums();
				}
			}
		}
		return js_redirect(module('forum')->_link_to_forum($_GET['id']));
	}
	
	/**
	* Posts Approve Unapprove
	*/
	function _posts_approve_unapprove() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		$topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.$_GET['id'].' LIMIT 1');
		if (empty($topic_info)) {
			return module('forum')->_show_error('No such topic!');
		}
		$forum_info = module('forum')->_forums_array[$topic_info['forum']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (($_POST['p_act'] == 'approve' && !module('forum')->USER_RIGHTS['approve_posts']) 
			|| ($_POST['p_act'] == 'unapprove' && !module('forum')->USER_RIGHTS['unapprove_posts'])
			|| (FORUM_IS_MODERATOR && !module('forum')->_moderate_forum_allowed($forum_info['id']))
		) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			$new_approve = $_POST['p_act'] == 'approve' ? 'a' : 'u';
			db()->query('UPDATE '.db('forum_posts').' SET status="'.$new_approve.'" WHERE topic='.intval($topic_info['id']).' AND id IN('.implode(',', $selected_ids).')');
			if (is_object($this->SYNC_OBJ)) {
				$this->SYNC_OBJ->_update_topic_record($topic_info['id']);
				$this->SYNC_OBJ->_update_forum_record($forum_info['id']);
				$this->SYNC_OBJ->_fix_subforums();
			}
		}
		return js_redirect('./?object=forum&action=view_topic&id='.$_GET['id']);
	}
	
	/**
	*/
	function _posts_delete() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		$topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.$_GET['id'].' LIMIT 1');
		if (empty($topic_info)) {
			return module('forum')->_show_error('No such topic!');
		}
		$forum_info = module('forum')->_forums_array[$topic_info['forum']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (FORUM_IS_MODERATOR) {
			if (!module('forum')->_moderate_forum_allowed($forum_info['id'])) {
				return module('forum')->_show_error('You are not allowed to perform this action');
			}
		}
		if (!empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			if (in_array($topic_info['first_post_id'], $selected_ids)) {
				return module('forum')->_show_error('You cannot delete first post in the topic!');
			}
			db()->query('DELETE FROM '.db('forum_posts').' WHERE topic='.intval($topic_info['id']).' AND id IN('.implode(',', $selected_ids).')');
			if (is_object($this->SYNC_OBJ)) {
				$this->SYNC_OBJ->_update_topic_record($topic_info['id']);
				$this->SYNC_OBJ->_update_forum_record($forum_info['id']);
				$this->SYNC_OBJ->_fix_subforums();
			}
		}
		return js_redirect('./?object=forum&action=view_topic&id='.$_GET['id']);
	}
	
	/**
	*/
	function _posts_move() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		if (!module('forum')->USER_RIGHTS['move_posts']) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		$topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.$_GET['id'].' LIMIT 1');
		if (empty($topic_info)) {
			return module('forum')->_show_error('No such topic!');
		}
		$forum_info = module('forum')->_forums_array[$topic_info['forum']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (FORUM_IS_MODERATOR) {
			if (!module('forum')->_moderate_forum_allowed($forum_info['id'])) {
				return module('forum')->_show_error('You are not allowed to perform this action');
			}
		}
		if (empty($_POST['new_forum_id']) && !empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			if (in_array($topic_info['first_post_id'], $selected_ids)) {
				return module('forum')->_show_error('You cannot move first post in the topic!');
			}
			$Q = db()->query('SELECT * FROM '.db('forum_posts').' WHERE id IN('.implode(',', $selected_ids).')');
			while ($A = db()->fetch_assoc($Q)) $posts_array[$A['id']] = $A;
			foreach ((array)$selected_ids as $post_id) {
				$selected_posts[$post_id] = array(
					'post_id'		=> $post_id,
					'user_name'		=> _prepare_html($posts_array[$post_id]['user_name']),
					'post_date'		=> module('forum')->_show_date($posts_array[$post_id]['created'], 'post_date'),
					'post_text'		=> _class('bb_codes')->_process_text($posts_array[$post_id]['text']),
				);
			}
			$replace = array(
				'form_action'		=> './?object=forum&action='.$_GET['action'].'&id='.$_GET['id']._add_get(array('page')),
				'forum_id'			=> intval($forum_info['id']),
				'topic_id'			=> intval($topic_info['id']),
				'old_forum_name'	=> $forum_info['name'],
				'old_topic_name'	=> $topic_info['name'],
				'posts'				=> $selected_posts,
			);
			return tpl()->parse('forum'.'/admin/move_posts_main', $replace);
		} else {
			$old_topic_id	= $topic_info['id'];
			$old_forum_id	= $forum_info['id'];
			$_POST['topic_url'] = trim($_POST['topic_url']);
			if (!empty($_POST['topic_url'])) {
				if (is_numeric($_POST['topic_url']) && !empty($_POST['topic_url'])) {
					$new_topic_id = intval($_POST['topic_url']);
				} elseif (preg_match('/^http:\/\/(.+?)[\/=]*?view_topic[\/=]*?(&id=)?([0-9]+).*?$/ims', $_POST['topic_url'], $m)) {
					$new_topic_id = intval($m[3]);
				}
			}
			if (empty($new_topic_id)) {
				return module('forum')->_show_error('Wrong topic ID!');
			}
			$new_topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.intval($new_topic_id).' LIMIT 1');
			if (empty($new_topic_info['id'])) {
				return module('forum')->_show_error('No such topic ID!');
			}
			$new_forum_info	= module('forum')->_forums_array[$new_topic_info['forum']];
			$new_forum_id = $new_forum_info['id'];
			if ($new_topic_id != $old_topic_id) {
				foreach ((array)$_POST as $k => $v) {
					if (substr($k, 0, 5) == 'post_' && $v == 1) {
						$selected_ids[] = intval(substr($k, 5));
					}
				}
				if (is_array($selected_ids)) {
					if (in_array($topic_info['first_post_id'], $selected_ids)) {
						return module('forum')->_show_error('You cannot move first post in the topic!');
					}
					$Q = db()->query('SELECT * FROM '.db('forum_posts').' WHERE id IN('.implode(',', $selected_ids).')');
					while ($A = db()->fetch_assoc($Q)) $posts_array[$A['id']] = $A;
				}
				if (is_array($selected_ids)) {
					db()->query('UPDATE '.db('forum_posts').' SET topic='.intval($new_topic_id).' WHERE id IN('.implode(',', $selected_ids).')');
				}
				if (is_object($this->SYNC_OBJ)) {
					$this->SYNC_OBJ->_update_topic_record($old_topic_id);
					$this->SYNC_OBJ->_update_topic_record($new_topic_id);
					$this->SYNC_OBJ->_update_forum_record($old_forum_id);
					$this->SYNC_OBJ->_update_forum_record($new_forum_id);
					$this->SYNC_OBJ->_fix_subforums();
				}
			}
		}
		return js_redirect('./?object=forum&action=view_topic&id='.$_GET['id']);
	}
	
	/**
	*/
	function _posts_split() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		if (!module('forum')->USER_RIGHTS['split_merge']) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		$topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.$_GET['id'].' LIMIT 1');
		if (empty($topic_info)) {
			return module('forum')->_show_error('No such topic!');
		}
		$forum_info = module('forum')->_forums_array[$topic_info['forum']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (FORUM_IS_MODERATOR) {
			if (!module('forum')->_moderate_forum_allowed($forum_info['id'])) {
				return module('forum')->_show_error('You are not allowed to perform this action');
			}
		}
		if (empty($_POST['new_forum_id']) && !empty($_POST['selected_ids'])) {
			$selected_ids = explode(',', $_POST['selected_ids']);
			if (in_array($topic_info['first_post_id'], $selected_ids)) {
				return module('forum')->_show_error('You cannot move first post in the topic!');
			}
			$Q = db()->query('SELECT * FROM '.db('forum_posts').' WHERE id IN('.implode(',', $selected_ids).')');
			while ($A = db()->fetch_assoc($Q)) $posts_array[$A['id']] = $A;
			foreach ((array)$selected_ids as $post_id) {
				$selected_posts[$post_id] = array(
					'post_id'		=> $post_id,
					'user_name'		=> _prepare_html($posts_array[$post_id]['user_name']),
					'post_date'		=> module('forum')->_show_date($posts_array[$post_id]['created'], 'post_date'),
					'post_text'		=> _class('bb_codes')->_process_text($posts_array[$post_id]['text']),
				);
			}
			$replace = array(
				'form_action'		=> './?object=forum&action='.$_GET['action'].'&id='.$_GET['id']._add_get(array('page')),
				'forum_id'			=> intval($forum_info['id']),
				'topic_id'			=> intval($topic_info['id']),
				'old_forum_name'	=> $forum_info['name'],
				'old_topic_name'	=> $topic_info['name'],
				'forums_box'		=> $this->_forums_box(),
				'posts'				=> $selected_posts,
			);
			return tpl()->parse('forum'.'/admin/split_topic_main', $replace);
		} else {
			$old_topic_id	= $topic_info['id'];
			$old_forum_id	= $forum_info['id'];
			$new_forum_id	= intval($_POST['new_forum_id']);
			foreach ((array)$_POST as $k => $v) {
				if (substr($k, 0, 5) == 'post_' && $v == 1) {
					$selected_ids[] = intval(substr($k, 5));
				}
			}
			if (is_array($selected_ids)) {
				if (in_array($topic_info['first_post_id'], $selected_ids)) {
					return module('forum')->_show_error('You cannot move first post in the topic!');
				}
				$Q = db()->query('SELECT * FROM '.db('forum_posts').' WHERE id IN('.implode(',', $selected_ids).')');
				while ($A = db()->fetch_assoc($Q)) $posts_array[$A['id']] = $A;
				$first_post_id = intval($selected_ids[0]);
				if (!empty($_POST['new_title']) && !empty($_POST['new_forum_id']) && !empty($first_post_id)) {
					db()->insert('forum_topics', array(
						'forum'				=> intval($new_forum_id),
						'name'				=> _es($_POST['new_title']),
						'desc'				=> _es($_POST['new_desc']),
						'user_id'			=> intval($posts_array[$first_post_id]['user_id']),
						'user_name'			=> _es($posts_array[$first_post_id]['user_name']),
						'created'			=> time(),
						'first_post_id'		=> intval($first_post_id),
						'last_post_id'		=> intval($first_post_id),
						'last_poster_id'	=> intval($posts_array[$first_post_id]['user_id']),
						'last_poster_name'	=> _es($posts_array[$first_post_id]['user_name']),
						'last_post_date'	=> time(),
						'approved'			=> 1,
					));
					$new_topic_id = intval(db()->insert_id());
				}
			}
			if (empty($new_topic_id)) {
				return module('forum')->_show_error('Wrong topic ID!');
			}
			$new_topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.intval($new_topic_id).' LIMIT 1');
			if (empty($new_topic_info['id'])) {
				return module('forum')->_show_error('No such topic ID!');
			}
			if (is_array($selected_ids)) {
				db()->query('UPDATE '.db('forum_posts').' SET topic='.intval($new_topic_id).' WHERE id IN('.implode(',', $selected_ids).')');
			}
			if (is_object($this->SYNC_OBJ)) {
				$this->SYNC_OBJ->_update_topic_record($old_topic_id);
				$this->SYNC_OBJ->_update_topic_record($new_topic_id);
				$this->SYNC_OBJ->_update_forum_record($old_forum_id);
				$this->SYNC_OBJ->_update_forum_record($new_forum_id);
				$this->SYNC_OBJ->_fix_subforums();
			}
		}
		return js_redirect('./?object=forum&action=view_topic&id='.$_GET['id']);
	}
	
	/**
	*/
	function _posts_merge() {
		if (empty($_GET['id'])) {
			return module('forum')->_show_error('No ID!');
		}
		if (!module('forum')->USER_RIGHTS['split_merge']) {
			return module('forum')->_show_error('You are not allowed to perform this action');
		}
		$topic_info = db()->query_fetch('SELECT * FROM '.db('forum_topics').' WHERE id='.$_GET['id'].' LIMIT 1');
		if (empty($topic_info)) {
			return module('forum')->_show_error('No such topic!');
		}
		$forum_info = module('forum')->_forums_array[$topic_info['forum']];
		if (empty($forum_info['id'])) {
			return module('forum')->_show_error('No such forum!');
		}
		if (FORUM_IS_MODERATOR) {
			if (!module('forum')->_moderate_forum_allowed($forum_info['id'])) {
				return module('forum')->_show_error('You are not allowed to perform this action');
			}
		}
		$selected_ids = explode(',', trim($_POST['selected_ids']));
		if (in_array($topic_info['first_post_id'], $selected_ids)) {
			return module('forum')->_show_error('You cannot merge first post in the topic!');
		}
		if (!empty($selected_ids)) {
			$Q = db()->query('SELECT * FROM '.db('forum_posts').' WHERE id IN('.implode(',', $selected_ids).')');
			while ($A = db()->fetch_assoc($Q)) $posts_array[$A['id']] = $A;
		}
		if (empty($_POST['merged_post_id']) && !empty($_POST['selected_ids'])) {
			foreach ((array)$posts_array as $post_info) {
				$text_merged[] = $post_info['text'];
				$authors_array[$post_info['user_name']]	= _prepare_html($post_info['user_name']).($post_info['user_id'] ? ' (#'.$post_info['user_id'].')' : '');
				$dates_array[$post_info['created']]		= module('forum')->_show_date($post_info['created'], 'post_date');
				if (!empty($post_info['edit_time'])) {
					$dates_array[$post_info['edit_time']]	= module('forum')->_show_date($post_info['edit_time'], 'edit_time');
				}
			}
			$replace = array(
				'form_action'	=> './?object=forum&action='.$_GET['action'].'&id='.$_GET['id']._add_get(array('page')),
				'dates_box'		=> common()->select_box('new_date', $dates_array, $selected, false, 2, '', false),
				'authors_box'	=> common()->select_box('new_author', $authors_array, $selected, false, 2, '', false),
				'selected_ids'	=> implode(',', $selected_ids),
				'merged_post_id'=> intval(array_pop($selected_ids)),
				'text'			=> implode(PHP_EOL.PHP_EOL, $text_merged),
			);
			return tpl()->parse('forum'.'/admin/merge_posts_main', $replace);
		} else {
			$old_topic_id		= $topic_info['id'];
			$old_forum_id		= $forum_info['id'];
			$merged_post_id		= intval($_POST['merged_post_id']);
			$merged_post_info	= $posts_array[$merged_post_id];
			$new_author_name	= $_POST['new_author'];
			foreach ((array)$posts_array as $post_info) {
				if ($post_info['user_name'] == $new_author_name) {
					$new_user_id = $post_info['user_id'];
					break;
				}
			};
			if (!empty($merged_post_id)) {
				db()->update(db('forum_posts'), _es(array( 
					'text'		=> $_POST['new_text'],
					'user_name'	=> $new_author_name,
					'user_id'	=> intval($new_user_id),
					'created'	=> intval($_POST['new_date']),
				)), 'id='.intval($merged_post_id));
			}
			if (is_array($selected_ids)) {
				foreach ((array)$posts_array as $post_id => $post_info) {
					if ($post_info['id'] == $merged_post_id) {
						continue;
					}
					$ids_to_delete[$post_id] = $post_id;
				}
			}
			if (is_array($ids_to_delete)) {
				db()->query('DELETE FROM '.db('forum_posts').' WHERE id IN('.implode(',', $ids_to_delete).')');
			}
			if (is_object($this->SYNC_OBJ)) {
				$this->SYNC_OBJ->_update_topic_record($old_topic_id);
				$this->SYNC_OBJ->_update_forum_record($old_forum_id);
				$this->SYNC_OBJ->_fix_subforums();
			}
		}
		return js_redirect('./?object=forum&action=view_topic&id='.$_GET['id']);
	}

	/**
	*/
	function _forums_box($name_in_form = 'new_forum_id', $selected = '') {
		$forum_divider	= '&nbsp;&nbsp;&#0124;-- ';
		$forums_array	= array();
		foreach ((array)module('forum')->_forum_cats_array as $cat_info) {
			foreach ((array)module('forum')->_forums_array as $forum_info) {
				if ($forum_info['category'] != $cat_info['id']) {
					continue;
				}
				$forums_array[$cat_info['name']][$forum_info['id']] = $forum_divider. $forum_info['name'];
			}
		}
		return common()->select_box($name_in_form, $forums_array, $selected, false, 2, '', false);
	}	
}
