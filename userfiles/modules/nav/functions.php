<?

if (!defined("MODULE_DB_TABLE_SHOP")) {
	define('MODULE_DB_TABLE_MENUS', TABLE_PREFIX . 'menus');
}

action_hook('mw_edit_page_admin', 'mw_print_admin_menu_selector');

function mw_print_admin_menu_selector($params = false) {
	//d($params);
	$add = '';
	if (isset($params['id'])) {
		$add = '&content_id=' . $params['id'];
	}
	print module('data-wrap=1&data-type=nav/edit_page_menus' . $add);
}

function get_menu_items($params = false) {
	$table = MODULE_DB_TABLE_MENUS;
	$params2 = array();
	if ($params == false) {
		$params = array();
	}
	if (is_string($params)) {
		$params = parse_str($params, $params2);
		$params = $params2;
	}
	$params['table'] = $table;
	$params['item_type'] = 'menu_item';
	return get($params);
}

function get_menu($params = false) {

	$table = MODULE_DB_TABLE_MENUS;

	$params2 = array();
	if ($params == false) {
		$params = array();
	}
	if (is_string($params)) {
		$params = parse_str($params, $params2);
		$params = $params2;
	}
	//$table = MODULE_DB_TABLE_SHOP_ORDERS;
	$params['table'] = $table;
	$params['item_type'] = 'menu';
	//$params['debug'] = 'menu';
	return get($params);
}

api_expose('add_new_menu');
function add_new_menu($data_to_save) {

	$id = is_admin();
	if ($id == false) {
		error('Error: not logged in as admin.');
	}

	if (isset($data_to_save['menu_id'])) {
		$data_to_save['id'] = intval($data_to_save['menu_id']);
	}
	$table = MODULE_DB_TABLE_MENUS;

	$data_to_save['table'] = $table;
	$data_to_save['item_type'] = 'menu';

	$save = save_data($table, $data_to_save);

	cache_clean_group('menus/global');

	return $save;

}

function menu_tree($menu_id, $maxdepth = false) {

	static $passed_ids;
	if (!is_array($passed_ids)) {
		$passed_ids = array();
	}

	$params = array();
	$params['item_parent'] = $menu_id;
	// $params ['item_parent<>'] = $menu_id;
	$menu_id = intval($menu_id);
	$params_order = array();
	$params_order['position'] = 'ASC';

	$table_menus = MODULE_DB_TABLE_MENUS;

	$sql = "SELECT * from {$table_menus}
	where parent_id=$menu_id
	and item_type='menu_item'
	 
	order by position ASC ";
	//d($sql);
	$q = db_query($sql, __FUNCTION__ . crc32($sql), 'menus/' . $menu_id);

	// $data = $q;
	if (empty($q)) {
		return false;
	}
	$active_class = '';
	// $to_print = '<ul class="menu" id="menu_item_' .$menu_id . '">';
	$to_print = '<ul class="menu menu_' . $menu_id . '">';

	$cur_depth = 0;
	foreach ($q as $item) {
		$full_item = $item;

		$title = '';
		$url = '';

		if (intval($item['content_id']) > 0) {
			$cont = get_content_by_id($item['content_id']);
			if (isarr($cont)) {
				$title = $cont['title'];
				$url = content_link($cont['id']);
			}
		} else if (intval($item['taxonomy_id']) > 0) {
			$cont = get_category_by_id($item['taxonomy_id']);
			if (isarr($cont)) {
				$title = $cont['title'];
				$url = category_link($cont['id']);
			}
		} else {
			$title = $item['title'];
			$url = $item['url'];
		}

		if ($title != '') {
			//$full_item['the_url'] = page_link($full_item['content_id']);
			$to_print .= '<li class="menu_element ' . ' ' . $active_class . '" id="menu_item_' . $item['id'] . '">';
			$to_print .= '<a class="menu_element_link ' . ' ' . $active_class . '" href="' . $url . '">' . $title . '</a>';
			$to_print .= '</li>';
			if (in_array($item['id'], $passed_ids) == false) {

				if ($maxdepth == false) {
					$test1 = menu_tree($item['id']);
					if (strval($test1) != '') {
						$to_print .= strval($test1);
					}
				} else {

					if (($maxdepth != false) and ($cur_depth <= $maxdepth)) {
						$test1 = menu_tree($item['id']);
						if (strval($test1) != '') {
							$to_print .= strval($test1);
						}
					}
				}
			}

		}

		//	$passed_ids[] = $item['id'];
		// }
		// }
		$cur_depth++;
	}

	// print "[[ $time ]]seconds\n";
	$to_print .= '</ul>';

	return $to_print;
}

function is_in_menu($menu_id = false, $content_id = false) {
	if ($menu_id == false or $content_id == false) {
		return false;
	}

	$menu_id = intval($menu_id);
	$content_id = intval($content_id);
	$check = get_menu_items("limit=1&count=1&parent_id={$menu_id}&content_id=$content_id");
	$check = intval($check);
	if ($check > 0) {
		return true;
	} else {
		return false;
	}
}

api_hook('save_content', 'add_content_to_menu');

function add_content_to_menu($content_id) {
	$id = is_admin();
	if ($id == false) {
		error('Error: not logged in as admin.');
	}
	$content_id = intval($content_id);
	if ($content_id == 0) {
		return;
	}
	$table_menus = MODULE_DB_TABLE_MENUS;
	if (isset($_REQUEST['add_content_to_menu']) and is_array($_REQUEST['add_content_to_menu'])) {
		$add_to_menus = $_REQUEST['add_content_to_menu'];
		$add_to_menus_int = array();
		foreach ($add_to_menus as $value) {
			if ($value == 'remove_from_all') {
				$sql = "delete from {$table_menus}
	where 
	  item_type='menu_item'
	 and content_id={$content_id}
	  ";
				//d($sql);
				cache_clean_group('menus');
				$q = db_q($sql);
				return;
			}

			$value = intval($value);
			if ($value > 0) {
				$add_to_menus_int[] = $value;
			}
		}

	}

	if (isset($add_to_menus_int) and isarr($add_to_menus_int)) {
		$add_to_menus_int_implode = implode(',', $add_to_menus_int);
		$sql = "delete from {$table_menus}
			where parent_id not in ($add_to_menus_int_implode)
			and item_type='menu_item'
			 and content_id={$content_id}
	  ";

		$q = db_q($sql);

		foreach ($add_to_menus_int as $value) {
			$check = get_menu_items("limit=1&count=1&parent_id={$value}&content_id=$content_id");
			//d($check);
			if ($check == 0) {
				$save = array();
				$save['item_type'] = 'menu_item';
				//	$save['debug'] = $table_menus;
				$save['parent_id'] = $value;
				$save['url'] = '';
				$save['content_id'] = $content_id;
				save_data($table_menus, $save);
			}
		}
		cache_clean_group('menus/global');
	}

}
