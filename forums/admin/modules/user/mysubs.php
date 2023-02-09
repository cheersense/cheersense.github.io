<?php

	/*
	 *	MyBBPlugins
	 *	http://www.mybbplug.in/s/
	 *
	 *	MySubscriptions
	 *	Created by Ethan at MyBBPlugins
	 *	Thanks to Jammerx2 for inspiration/approval
	 *
	 *  This plugin and its contents are free for use.
	 *
	 */

	// Prevent users from accessing this file directly.
	if(!defined("IN_MYBB")) die( "You are not allowed to view this file directly.<br /><br />Please make sure IN_MYBB is defined." );
	
	$page->add_breadcrumb_item($lang->mysubs, "index.php?module=user-mysubs");
	
	$sub_tabs['manage_subs'] = array(
		'title' => $lang->manage_subs,
		'link' => "index.php?module=user-mysubs",
		'description' => $lang->manage_subs_desc
	);
	$sub_tabs['add'] = array(
		'title' => $lang->add_sub,
		'link' => "index.php?module=user-mysubs&amp;action=add",
		'description' => $lang->add_sub_desc
	);
	$sub_tabs['notif'] = array(
		'title' => $lang->notif,
		'link' => "index.php?module=user-mysubs&amp;action=notifications",
		'description' => $lang->notif_desc
	);
	$sub_tabs['settings'] = array(
		'title' => $lang->settngs,
		'link' => "index.php?module=user-mysubs&amp;action=settings",
		'description' => $lang->settngs_desc
	);
	
	if(!$mybb->input['action'])
	{
		$page->output_header($lang->manage_subs);

		$page->output_nav_tabs($sub_tabs, 'manage_subs');

		$table = new Table;
		$table->construct_header($lang->subscription);
		$table->construct_header($lang->price_length);
		$table->construct_header($lang->controls, array('width' => '100', 'style' => 'text-align: center;'));
	
		$subs_result = $db->simple_select('mysubs');
		while($sub = $db->fetch_array($subs_result))
		{
			if($sub['active'] == 1)
				$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.gif\" alt=\"({$lang->active})\" title=\"{$lang->active}\"  style=\"vertical-align: middle;\" /> ";
			else
				$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.gif\" alt=\"({$lang->alt_unactive})\" title=\"{$lang->alt_unactive}\"  style=\"vertical-align: middle;\" /> ";
			
			$price = array();
			$prices = unserialize($sub['price']);
			foreach($prices as $val)
			{
				if($val['l'] < 1) $lt = $lang->forever;
				else $lt = $val['l'].' '.(($val['lt'] == 'y') ? (($val['l'] > 1) ? $lang->years : $lang->year) : (($val['lt'] == 'm') ? (($val['l'] > 1) ? $lang->months : $lang->month) :  (($val['l'] > 1) ? $lang->days : $lang->day)));
				$price[] = $val['c'].' '.$sub['currency'].' / '.$lt;
			}
			
			$options = new PopupMenu("sub_{$sub['sid']}", $lang->options);
			$options->add_item($lang->edit_sub, "index.php?module=user-mysubs&amp;action=edit&amp;sid={$sub['sid']}");
			$options->add_item($lang->view_subs, "index.php?module=user-mysubs&amp;action=notifications&sid={$sub['sid']}");
			$options->add_item($lang->delete_sub, "index.php?module=user-mysubs&amp;action=delete&amp;sid={$sub['sid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_sub_deletion}')");
			
			$table->construct_cell("<div class=\"float_right\">{$icon}</div><div><strong><a href=\"index.php?module=user-mysubs&amp;action=edit&amp;sid={$sub['sid']}\">{$sub['name']}</a></strong><br /><small>{$sub['admin_desc']}</small></div>");
			$table->construct_cell(implode('<br />', $price));
			$table->construct_cell($options->fetch(), array("class" => "align_center"));
			$table->construct_row();
		}
		
		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->no_subs, array('colspan' => 3));
			$table->construct_row();
		}
		
		$table->output($lang->manage_subs);
	}
	else
	if($mybb->input['action'] == 'add')
	{
		$page->output_header($lang->add_sub);

		$page->output_nav_tabs($sub_tabs, 'add');

		$currency_options = array(
			'USD' => "USD (US Dollar)",
			'EUR' => "EUR (Euro)",
			'CAD' => "CAD (Canadian Dollar)"
		);
		
		// $query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
		foreach($cache->cache['usergroups'] as $usergroup)
		{
			if(intval($usergroup['gid']) != 1 && !$usergroup['isbannedgroup'])
			{
				$usergroup_options[$usergroup['gid']] = $usergroup['title'];
			}
		}
		
		if(isset($mybb->input['price_options']) && !isset($mybb->input['delete_price']))
		{
			$forevs = array();
			$others = array();
			foreach($mybb->input['price_options'] as $opt)
			{
				if(intval($opt['l']) < 1) $forevs[] = $opt;
				else $others[] = $opt;
			}
			if(!empty($forevs))
			{
				$c = array();
				foreach($forevs as $key => $val)
				{
					$c[$key] = $val['c'];
				}
				array_multisort($c, SORT_ASC, $forevs);
			}
			if(!empty($others))
			{
				$c = $l = $lt = array();
				foreach($others as $key => $val)
				{
					$c[$key] = $val['c'];
					$l[$key] = $val['l'];
					$lt[$key] = $val['lt'];
				}
				array_multisort($lt, SORT_ASC, $l, SORT_ASC, $c, SORT_ASC, $others);
			}
			$mybb->input['price_options'] = array();
			foreach($others as $a) $mybb->input['price_options'][] = $a;
			foreach($forevs as $a) $mybb->input['price_options'][] = $a;
		}
		
		if($mybb->request_method == 'post')
		{
			if(!isset($mybb->input['add']) && !isset($mybb->input['delete_price']))
			{
				$name = $db->escape_string($mybb->input['name']);
				if(empty($name) || rtrim($name) == '') $errors[] = $lang->error_empty_name;
				
				$price = $db->escape_string(serialize($mybb->input['price_options']));
				
				$currency = $db->escape_string($mybb->input['currency']);
				if(!in_array($currency, array_keys($currency_options))) $errors[] = $lang->error_invalid_currency;
				
				$y = abs(intval($mybb->input['duration_years']));
				$m = abs(intval($mybb->input['duration_months']));
				$d = abs(intval($mybb->input['duration_days']));
				$h = abs(intval($mybb->input['duration_hours']));
				$time = abs(intval(($y * 31536000) + ($m * 2592000) + ($d * 86400) + ($h * 3600)));
				if($y > 1000 || $m > 1000 || $d > 1000 || $h > 1000) $errors[] = $lang->error_duration_too_long;
				
				$new_group = intval($mybb->input['new_group']);
				if(!in_array($new_group, array_keys($usergroup_options))) $errors[] = $lang->error_invalid_group;
				
				if(is_array($mybb->input['usergroup_options']))
				{
					foreach($mybb->input['usergroup_options'] as $group)
					{
						$gid = intval($group['gid']);
						if(in_array($gid, array_keys($usergroup_options)))
						{
							$accepted[] = $gid;
						}
					}
				}
				$accepted = (empty($accepted) ? 'all' : implode(',', $accepted));
				
				$item_name = $db->escape_string($mybb->input['item_name']);
				if(!ctype_alnum($item_name) || count($item_name) > 127) $errors[] = $lang->error_invalid_item_name;
				
				// This is for recurring subscriptions, just make all options false for now.
				$recurring = 0; // ((intval($mybb->input['recurring']) == 1) ? 1 : 0);
				$active = ((intval($mybb->input['active']) == 1) ? 1 : 0);
				
				if(!$errors)
				{
					$new_sub = array(
						'name' => $name,
						'admin_desc' => $db->escape_string($mybb->input['admin_desc']),
						'description' => $db->escape_string($mybb->input['description']),
						'recurring' => $recurring,
						'price' => $price,
						'currency' => $currency,
						'new_group' => $new_group,
						'active' => $active,
						'accepted_gids' => $accepted,
						'item_name' => $item_name,
						'item_number' => 'sub_{$sid}'
					);
					
					$sid = $db->insert_query('mysubs', $new_sub);
					if(!$sid)
					{
						$errors[] = $lang->error_fail_save;
					}
					else
					{
						$update_inumber = array(
							'item_number' => "sub_{$sid}"
						);
						$db->update_query('mysubs', $update_inumber, "`sid`={$sid}");
						flash_message($lang->success_sub_add, 'success');
						admin_redirect("index.php?module=user-mysubs&amp;action=edit&amp;sid={$sid}");
					}
				}
			}
			else if(isset($mybb->input['delete_price']))
			{
				
			}
		}
	
		$form = new Form("index.php?module=user-mysubs&amp;action=add", "post");
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		
		if(!isset($mybb->input['recurring'])) $mybb->input['recurring'] = 0;
		
		$form_container = new FormContainer($lang->add_sub);
		
		$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->description, $lang->description_desc, $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->admin_desc, $lang->admin_desc_desc, $form->generate_text_box('admin_desc', $mybb->input['admin_desc'], array('id' => 'admin_desc')), 'admin_desc');
		$form_container->output_row($lang->new_group." <em>*</em>", $lang->new_group_desc, $form->generate_select_box('new_group', $usergroup_options, $mybb->input['new_group'], array('id' => 'new_group')), 'new_group');
		$form_container->output_row($lang->accepted_groups, $lang->accepted_groups_desc, $form->generate_select_box('accepted_groups[]', $usergroup_options, $mybb->input['accepted_groups'], array('id' => 'accepted_groups', 'multiple' => true, 'size' => 5)), 'accepted_groups');
		$form_container->output_row($lang->item_name." <em>*</em>", $lang->item_name_desc, $form->generate_text_box('item_name', $mybb->input['item_name'], array('id' => 'item_name')), 'item_name');
		$form_container->output_row($lang->active." <em>*</em>", $lang->active_desc, $form->generate_yes_no_radio("active", $mybb->input['active'], true));
		
		$form_container->end();
		
		$price_container = new FormContainer($lang->price_settings);
		
		$price_container->output_row($lang->currency." <em>*</em>", $lang->currency_desc, $form->generate_select_box('currency', $currency_options, $mybb->input['currency'], array('id' => 'currency')), 'currency');
		
		$time_options = array(
			'd' => $lang->days,
			'm' => $lang->months,
			'y' => $lang->years
		);
		
		$k = 0;
		if(isset($mybb->input['price_options']) && is_array($mybb->input['price_options']))
		{
			foreach($mybb->input['price_options'] as $key => $po)
			{
				if(!isset($mybb->input['delete_price'][$key]))
				{
					$price_options[$key] = '<div style="margin-bottom: 5px;">';
					$price_options[$key] .= '<strong>'.$lang->cost.':</strong> '.$form->generate_text_box("price_options[{$key}][c]", $po['c'], array('id' => "price_options[{$key}][c]", 'style' => 'width: 70px;'));
					$price_options[$key] .= '</div>';
					$price_options[$key] .= '<div><strong>'.$lang->length.':</strong> '.
						$form->generate_text_box("price_options[{$key}][l]", $po['l'], array('id' => "price_options[{$key}][l]", 'style' => 'width: 30px;')).
						' '.$form->generate_select_box("price_options[{$key}][lt]", $time_options, $po['lt'], array('id' => "price_options[{$key}][lt]")).'</div>';
					$price_options[$key] .= '<div>'.$form->generate_submit_button($lang->delete, array('id' => "delete_price[{$key}]", 'name' => "delete_price[{$key}]")).'</div>';
				}
			}
			$k = $key + 1;
		}
		$kc = intval($mybb->input['add_number']);
		while($kc > 1)
		{
			$price_options[$k] = '<div style="margin-bottom: 5px;">';
			$price_options[$k] .= '<strong>'.$lang->cost.':</strong> '.$form->generate_text_box("price_options[{$k}][c]", '0.00', array('id' => "price_options[{$k}][c]", 'style' => 'width: 70px;'));
			$price_options[$k] .= '</div>';
			$price_options[$k] .= '<div><strong>'.$lang->length.':</strong> '.
				$form->generate_text_box("price_options[{$k}][l]", '0', array('id' => "price_options[{$k}][l]", 'style' => 'width: 30px;')).
				' '.$form->generate_select_box("price_options[{$k}][lt]", $time_options, $mybb->input["price_options[{$k}][lt]"], array('id' => "price_options[{$k}][lt]")).'</div>';
			$price_options[$k] .= '<div>'.$form->generate_submit_button($lang->delete, array('id' => "delete_price[{$k}]", 'name' => "delete_price[{$k}]")).'</div>';
			--$kc; ++$k;
		}
		$price_options[] = '<center>'.$form->generate_submit_button($lang->add, array('id' => 'add', 'name' => 'add')).$form->generate_text_box("add_number", '1', array('id' => "add_number", 'style' => 'width: 20px;')).'</center>';
		
		$price_container->output_row($lang->price." <em>*</em>", $lang->price_desc, implode('<br /><br />', $price_options), 'price_options');
		
		// This is for recurring subscriptions, just make all options false for now.
		// $price_container->output_row($lang->recurring." <em>*</em>", $lang->recurring_desc, $form->generate_yes_no_radio("recurring", $mybb->input['recurring'], true));
		
		$price_container->end();
		
		$buttons[] = $form->generate_submit_button($lang->save_sub);
		
		$form->output_submit_wrapper($buttons);

		$form->end();
	}
	else
	if($mybb->input['action'] == 'delete')
	{
		$sid = intval($mybb->input['sid']);
		
		$query = $db->simple_select("mysubs", "*", "sid='{$sid}'");
		$sub = $db->fetch_array($query);

		if(!$sub)
		{
			flash_message($lang->error_invalid_subscription, 'error');
			admin_redirect("index.php?module=user-mysubs");
		}
		
		if(!$db->delete_query('mysubs', "`sid`={$sid}"))
		{
			flash_message($lang->error_fail_delete, 'error');
			admin_redirect("index.php?module=user-mysubs");
		}
		else
		{
			flash_message($lang->success_sub_delete, 'success');
			admin_redirect("index.php?module=user-mysubs");
		}
	}
	else
	if($mybb->input['action'] == 'edit')
	{
		$page->output_header($lang->edit_sub);

		$page->output_nav_tabs($sub_tabs, 'manage_subs');
		
		$sid = intval($mybb->input['sid']);
		
		$query = $db->simple_select("mysubs", "*", "sid='{$sid}'");
		$sub = $db->fetch_array($query);

		if(!$sub)
		{
			flash_message($lang->error_invalid_subscription, 'error');
			admin_redirect("index.php?module=user-mysubs");
		}
		
		$currency_options = array(
			'USD' => "USD (US Dollar)",
			'EUR' => "EUR (Euro)",
			'CAD' => "CAD (Canadian Dollar)"
		);
		
		// $query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
		foreach($cache->cache['usergroups'] as $usergroup)
		{
			if(intval($usergroup['gid']) != 1 && !$usergroup['isbannedgroup'])
			{
				$usergroup_options[$usergroup['gid']] = $usergroup['title'];
			}
		}
		
		if(isset($mybb->input['price_options']))
		{
			$forevs = array();
			$others = array();
			foreach($mybb->input['price_options'] as $_id => $opt)
			{
				if(empty($mybb->input['delete_price'][$_id]))
				{
					if(intval($opt['l']) < 1) $forevs[] = $opt;
					else $others[] = $opt;
				}
			}
			if(!empty($forevs))
			{
				$c = array();
				foreach($forevs as $key => $val)
				{
					$c[$key] = $val['c'];
				}
				array_multisort($c, SORT_ASC, $forevs);
			}
			if(!empty($others))
			{
				$c = $l = $lt = array();
				foreach($others as $key => $val)
				{
					$c[$key] = $val['c'];
					$l[$key] = $val['l'];
					$lt[$key] = $val['lt'];
				}
				array_multisort($lt, SORT_ASC, $l, SORT_ASC, $c, SORT_ASC, $others);
			}
			$mybb->input['price_options'] = array();
			foreach($others as $a) $mybb->input['price_options'][] = $a;
			foreach($forevs as $a) $mybb->input['price_options'][] = $a;
		}
		
		if($mybb->request_method == 'post')
		{
			if(!isset($mybb->input['add']))
			{
				$name = $db->escape_string($mybb->input['name']);
				if(empty($name) || rtrim($name) == '') $errors[] = $lang->error_empty_name;
				
				$price = $db->escape_string(serialize($mybb->input['price_options']));
				
				$currency = $db->escape_string($mybb->input['currency']);
				if(!in_array($currency, array_keys($currency_options))) $errors[] = $lang->error_invalid_currency;
				
				$new_group = intval($mybb->input['new_group']);
				if(!in_array($new_group, array_keys($usergroup_options))) $errors[] = $lang->error_invalid_group;
				
				if(!empty($mybb->input['accepted_groups']))
				{
					foreach($mybb->input['accepted_groups'] as $group)
					{
						$gid = intval($group['gid']);
						if(in_array($gid, array_keys($usergroup_options)))
						{
							$accepted[] = $gid;
						}
					}
				}
				$accepted = (empty($accepted) ? 'all' : implode(',', $accepted));
				
				$item_name = $db->escape_string($mybb->input['item_name']);
				if(!ctype_alnum($item_name) || count($item_name) > 127) $errors[] = $lang->error_invalid_item_name;
				
				// This is for recurring subscriptions, just make all options false for now.
				$recurring = 0; // ((intval($mybb->input['recurring']) == 1) ? 1 : 0);
				$active = ((intval($mybb->input['active']) == 1) ? 1 : 0);
				
				if(!$errors)
				{
					$new_sub = array(
						'name' => $name,
						'admin_desc' => $db->escape_string($mybb->input['admin_desc']),
						'description' => $db->escape_string($mybb->input['description']),
						'recurring' => $recurring,
						'price' => $price,
						'currency' => $currency,
						'new_group' => $new_group,
						'active' => $active,
						'accepted_gids' => $accepted,
						'item_name' => $item_name,
						'item_number' => "sub_{$sid}"
					);
					
					if(!$db->update_query('mysubs', $new_sub, "`sid`={$sid}"))
					{
						$errors[] = $lang->error_fail_save;
					}
					else
					{
						flash_message($lang->success_save, 'success');
						admin_redirect("index.php?module=user-mysubs&amp;action=edit&amp;sid={$sid}");
					}
				}
			}
		}
		
		foreach($sub as $key => $val)
		{
			if(!isset($mybb->input[$key])) $mybb->input[$key] = $val;
		}
		if(!isset($mybb->input['price_options'])) $mybb->input['price_options'] = unserialize($sub['price']);
		if(!isset($mybb->input['accepted_groups'])) $mybb->input['accepted_groups'] = explode(',', $sub['accepted_gids']);
	
		$form = new Form("index.php?module=user-mysubs&amp;action=edit&amp;sid={$sid}", "post");
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		
		if(!isset($mybb->input['recurring'])) $mybb->input['recurring'] = 0;
		
		$form_container = new FormContainer($lang->edit_sub);
		
		$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->description, $lang->description_desc, $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->admin_desc, $lang->admin_desc_desc, $form->generate_text_box('admin_desc', $mybb->input['admin_desc'], array('id' => 'admin_desc')), 'admin_desc');
		$form_container->output_row($lang->new_group." <em>*</em>", $lang->new_group_desc, $form->generate_select_box('new_group', $usergroup_options, $mybb->input['new_group'], array('id' => 'new_group')), 'new_group');
		$form_container->output_row($lang->accepted_groups, $lang->accepted_groups_desc, $form->generate_select_box('accepted_groups[]', $usergroup_options, $mybb->input['accepted_groups'], array('id' => 'accepted_groups', 'multiple' => true, 'size' => 5)), 'accepted_groups');
		$form_container->output_row($lang->item_name." <em>*</em>", $lang->item_name_desc, $form->generate_text_box('item_name', $mybb->input['item_name'], array('id' => 'item_name')), 'item_name');
		$form_container->output_row($lang->active." <em>*</em>", $lang->active_desc, $form->generate_yes_no_radio("active", $mybb->input['active'], true));
		
		$form_container->end();
		
		$price_container = new FormContainer($lang->price_settings);
		
		$price_container->output_row($lang->currency." <em>*</em>", $lang->currency_desc, $form->generate_select_box('currency', $currency_options, $mybb->input['currency'], array('id' => 'currency')), 'currency');
		
		$time_options = array(
			'd' => $lang->days,
			'm' => $lang->months,
			'y' => $lang->years
		);
		
		$k = 0;
		if(isset($mybb->input['price_options']) && is_array($mybb->input['price_options']))
		{
			foreach($mybb->input['price_options'] as $key => $po)
			{
				if(!isset($mybb->input['delete_price'][$key]))
				{
					$price_options[$key] = '<div style="margin-bottom: 5px;">';
					$price_options[$key] .= '<strong>'.$lang->cost.':</strong> '.$form->generate_text_box("price_options[{$key}][c]", $po['c'], array('id' => "price_options[{$key}][c]", 'style' => 'width: 70px;'));
					$price_options[$key] .= '</div>';
					$price_options[$key] .= '<div><strong>'.$lang->length.':</strong> '.
						$form->generate_text_box("price_options[{$key}][l]", $po['l'], array('id' => "price_options[{$key}][l]", 'style' => 'width: 30px;')).
						' '.$form->generate_select_box("price_options[{$key}][lt]", $time_options, $po['lt'], array('id' => "price_options[{$key}][lt]")).'</div>';
					$price_options[$key] .= '<div>'.$form->generate_submit_button($lang->delete, array('id' => "delete_price[{$key}]", 'name' => "delete_price[{$key}]")).'</div>';
				}
			}
			$k = $key + 1;
		}
		$kc = intval($mybb->input['add_number']);
		while($kc >= 1)
		{
			$price_options[$k] = '<div style="margin-bottom: 5px;">';
			$price_options[$k] .= '<strong>'.$lang->cost.':</strong> '.$form->generate_text_box("price_options[{$k}][c]", '0.00', array('id' => "price_options[{$k}][c]", 'style' => 'width: 70px;'));
			$price_options[$k] .= '</div>';
			$price_options[$k] .= '<div><strong>'.$lang->length.':</strong> '.
				$form->generate_text_box("price_options[{$k}][l]", '0', array('id' => "price_options[{$k}][l]", 'style' => 'width: 30px;')).
				' '.$form->generate_select_box("price_options[{$k}][lt]", $time_options, $mybb->input["price_options[{$k}][lt]"], array('id' => "price_options[{$k}][lt]")).'</div>';
			$price_options[$k] .= '<div>'.$form->generate_submit_button($lang->delete, array('id' => "delete_price[{$k}]", 'name' => "delete_price[{$k}]")).'</div>';
			--$kc; ++$k;
		}
		if(empty($price_options))
		{
			$price_options[$k] = '<div style="margin-bottom: 5px;">';
			$price_options[$k] .= '<strong>'.$lang->cost.':</strong> '.$form->generate_text_box("price_options[{$k}][c]", '0.00', array('id' => "price_options[{$k}][c]", 'style' => 'width: 70px;'));
			$price_options[$k] .= '</div>';
			$price_options[$k] .= '<div style="margin-bottom: 5px;"><strong>'.$lang->length.':</strong> '.
				$form->generate_text_box("price_options[{$k}][l]", '0', array('id' => "price_options[{$k}][l]", 'style' => 'width: 30px;')).
				' '.$form->generate_select_box("price_options[{$k}][lt]", $time_options, $mybb->input["price_options[{$k}][lt]"], array('id' => "price_options[{$k}][lt]")).'</div>';
			$price_options[$k] .= '<div>'.$form->generate_submit_button($lang->delete, array('id' => "delete_price[{$k}]", 'name' => "delete_price[{$k}]")).'</div>';
		}
		$price_options[] = '<center>'.$form->generate_submit_button($lang->add, array('id' => 'add', 'name' => 'add')).$form->generate_text_box("add_number", '1', array('id' => "add_number", 'style' => 'width: 20px;')).'</center>';
		
		$price_container->output_row($lang->price." <em>*</em>", $lang->price_desc, implode('<br /><br />', $price_options), 'price_options');
		
		// This is for recurring subscriptions, just make all options false for now.
		// $price_container->output_row($lang->recurring." <em>*</em>", $lang->recurring_desc, $form->generate_yes_no_radio("recurring", $mybb->input['recurring'], true));
		
		$price_container->end();
		
		$buttons[] = $form->generate_submit_button($lang->save_sub);
		
		$form->output_submit_wrapper($buttons);

		$form->end();
	}
	else
	if($mybb->input['action'] == 'notifications')
	{
		$page->output_header($lang->notif);

		$page->output_nav_tabs($sub_tabs, 'notif');
		
		if(isset($mybb->input['id']))
		{
			$result = $db->simple_select('mysubs_notifs', '*', '`id`='.intval($mybb->input['id']));
			$notif = $db->fetch_array($result);
			if(!$notif)
			{
				flash_message($lang->error_invalid_notif, 'error');
				admin_redirect("index.php?module=user-mysubs&amp;action=notifications");
			}
		}
		else
		{
			$table = new Table;
			$table->construct_header($lang->user, array('width' => '150'));
			$table->construct_header($lang->item, array('width' => '150'));
			$table->construct_header($lang->time, array('width' => '150'));
			$table->construct_header($lang->expiration, array('width' => '150'));
			$table->construct_header($lang->active, array('width' => '70'));
			$table->construct_header($lang->success, array('width' => '70'));
			$table->construct_header($lang->controls, array('width' => '100', 'style' => 'text-align: center;'));
			
			$query = $db->simple_select('mysubs_notifs', '*', '', array('order_by' => 'id', 'order_dir' => 'DESC'));
			while($notif = $db->fetch_array($query))
			{
				$user = get_user($notif['uid']);
				if(!isset($user['username'])) $user['username'] = $lang->na;
				$result = $db->simple_select('mysubs', '*', '`sid`='.intval(str_replace('sub_', '', $notif['item_number'])));
				$item = $db->fetch_array($result);
				if(!isset($item['name'])) $item['name'] = $lang->na;
				$time = my_date('F jS, Y', $notif['time']);
				$expiration = intval($notif['expiration']) > 0 ? my_date('F jS, Y', $notif['expiration']) : $lang->never;
				$active = $notif['active'] ? $lang->yes : $lang->no;
				$success = $notif['success'] ? $lang->yes : $lang->no;

				$options = new PopupMenu("sub_{$sub['sid']}", $lang->options);
				$options->add_item($lang->view_details, "index.php?module=user-mysubs&amp;action=notifications&amp;id={$notif['id']}");
				
				$table->construct_cell($user['username']);
				$table->construct_cell($item['name']);
				$table->construct_cell($time);
				$table->construct_cell($expiration);
				$table->construct_cell($active);
				$table->construct_cell($success);
				$table->construct_cell($options->fetch(), array('class' => 'align_center'));
				$table->construct_row();
			}
			
			if($table->num_rows() == 0)
			{
				$table->construct_cell($lang->no_notifs, array('colspan' => '7'));
				$table->construct_row();
			}
			$table->output($lang->notif);
		}
	}
	else
	if($mybb->input['action'] == 'settings')
	{
		$page->output_header($lang->settngs);

		$page->output_nav_tabs($sub_tabs, 'settings');
		
		if($mybb->request_method == 'post')
		{
			$query = $db->simple_select('mysubs_settings');
			while($set = $db->fetch_array($query))
			{
				$name = 'setting_'.$set['name'];
				if(isset($mybb->input[$name]))
					$db->update_query('mysubs_settings', array('value' => $db->escape_string($mybb->input[$name])), "`id` = $set[id]");
			}
			flash_message($lang->success_save, 'success');
			admin_redirect("index.php?module=user-mysubs&amp;action=settings");
		}
	
		$form = new Form("index.php?module=user-mysubs&amp;action=settings", "post");
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		
		$normal_container = new FormContainer($lang->basic_settings, '', '', 0, '', true);
		$advanced_container = new FormContainer($lang->advanced_settings, '', '', 0, '', true);
		
		$query = $db->simple_select('mysubs_settings');
		while($set = $db->fetch_array($query))
		{
			$container = ($set['cat'] == 'n') ? 'normal_container' : 'advanced_container';
			$name = 'setting_'.$set['name'];
			$lname = $lang->{$name};
			$desc = $lang->{'setting_'.$set['name'].'_desc'};
			$type = '';
			if(!isset($mybb->input[$name])) $mybb->input[$name] = $set['value'];
			switch($set['type'])
			{
				case 'yesno':
					$type = $form->generate_yes_no_radio($name, $mybb->input[$name], true);
					break;
				case 'textbox':
				default:
					$type = $form->generate_text_box($name, $mybb->input[$name], array('id' => $name), $name);
					break;
			}
			${$container}->output_row($lname, $desc, $type, $lname);
		}
		
		$normal_container->end();
		$advanced_container->end();
		
		$buttons[] = $form->generate_submit_button($lang->save_settings);
		
		$form->output_submit_wrapper($buttons);

		$form->end();
	}
	else
	{
		admin_redirect("index.php?module=user-mysubs");
	}
	$page->output_footer();

?>