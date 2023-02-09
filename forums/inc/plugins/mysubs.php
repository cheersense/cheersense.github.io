<?php

	/*
	 *	MySubscriptions
	 *	Created by Ethan DeLong
	 *
	 *	This plugin and its contents are free for use.
	 *
	 *	Like Pokemon? Check out http://www.pokemonforum.org!
	 */
	
	// Prevent users from accessing this file directly.
	if(!defined("IN_MYBB")) die( "You are not allowed to view this file directly.<br /><br />Please make sure IN_MYBB is defined." );

	$plugins->add_hook("admin_user_menu", "mysubs_menu");
	$plugins->add_hook("admin_user_action_handler", "mysubs_action_handler");
	$plugins->add_hook("misc_start", "mysubs_payment_page");
	$plugins->add_hook("misc_start", "mysubs_ipn_handler");
	$plugins->add_hook("usercp_menu", "mysubs_usercp_menu");
	
	function mysubs_info()
	{
		return array(
			'name'			=> 'BUY zMotan Cheats',
			'description'	=> 'Lets you implement paid subscriptions to groups within your forums.',
			'website'		=> 'http://www.pokemonforum.org',
			'author'		=> 'Ethan',
			'authorsite'	=> 'http://www.pokemonforum.org',
			'version'		=> '2.01',
			'compatibility' => '18*',
			'guid'			=> ''
		);
	}
	
	function mysubs_install()
	{
		global $cache, $db, $mybb;
		
		/* Create table for storing subscription options. */
		if(!$db->table_exists('mysubs'))
		{
			$mysubs_table = "CREATE TABLE `".TABLE_PREFIX."mysubs` (
				`sid` INT( 11 ) NOT NULL AUTO_INCREMENT ,
				`name` TEXT NOT NULL ,
				`admin_desc` TEXT NOT NULL ,
				`description` TEXT NOT NULL ,
				`recurring` TINYINT( 1 ) NOT NULL DEFAULT '0',
				`price` TEXT NOT NULL ,
				`currency` VARCHAR( 3 ) NOT NULL DEFAULT 'USD',
				`new_group` INT NOT NULL ,
				`active` TINYINT( 1 ) NOT NULL DEFAULT '1',
				`accepted_gids` TEXT NOT NULL ,
				`item_name` VARCHAR( 127 ) NOT NULL ,
				`item_number` VARCHAR( 127 ) NOT NULL ,
				`order` INT( 3 ) NOT NULL ,
				PRIMARY KEY ( `sid` )
				) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
			";
			$db->query($mysubs_table);
		}
		
		/* Create table for storing subscription updates. */
		if(!$db->table_exists('mysubs_notifs'))
		{
			$notifs_table = "CREATE TABLE `".TABLE_PREFIX."mysubs_notifs` (
				`id` INT( 11 ) NOT NULL AUTO_INCREMENT ,
				`uid` INT( 11 ) NOT NULL ,
				`item_number` VARCHAR( 127 ) NOT NULL ,
				`expiration` INT( 11 ) NOT NULL ,
				`email` TEXT NOT NULL ,
				`time` INT( 11 ) NOT NULL ,
				`success` TINYINT( 1 ) NOT NULL ,
				`log` TEXT NOT NULL ,
				`active` TINYINT( 1 ) NOT NULL ,
				`old_gid` INT( 3 ) NOT NULL DEFAULT '0',
				`new_gid` INT( 3 ) NOT NULL DEFAULT '0',
				`txn_type` TEXT NOT NULL ,
				`ipn_data` TEXT NOT NULL ,
				PRIMARY KEY ( `id` )
				) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
			";
			$db->query($notifs_table);
		}
		
		/* Create table for settings. */
		if(!$db->table_exists('mysubs_settings'))
		{
			$settings_table = "CREATE TABLE `".TABLE_PREFIX."mysubs_settings` (
				`id` INT( 11 ) NOT NULL AUTO_INCREMENT ,
				`name` TEXT NOT NULL ,
				`value` TEXT NOT NULL ,
				`type` TEXT NOT NULL ,
				`cat` VARCHAR( 1 ) NOT NULL ,
				`order` INT( 3 ) NOT NULL ,
				PRIMARY KEY ( `id` )
				) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
			";
			$db->query($settings_table);
		}
		
		/* Create default settings. */
		# Normal Settings #
		if(!$db->fetch_array($db->simple_select('mysubs_settings', '*', "`name` LIKE 'enabled'")))
		{
			$settings[] = array(
				'name' => 'enabled',
				'value' => '0',
				'type' => 'yesno',
				'cat' => 'n',
				'order' => 0
			);
		}
		if(!$db->fetch_array($db->simple_select('mysubs_settings', '*', "`name` LIKE 'pp_email'")))
		{
			$settings[] = array(
				'name' => 'pp_email',
				'value' => 'something@something.com',
				'type' => 'textbox',
				'cat' => 'n',
				'order' => 1
			);
		}
		# Advanced Settings #
		if(!$db->fetch_array($db->simple_select('mysubs_settings', '*', "`name` LIKE 'use_ssl'")))
		{
			$settings[] = array(
				'name' => 'use_ssl',
				'value' => '0',
				'type' => 'yesno',
				'cat' => 'a',
				'order' => 0
			);
		}
		if(!$db->fetch_array($db->simple_select('mysubs_settings', '*', "`name` LIKE 'use_fsock'")))
		{
			$settings[] = array(
				'name' => 'use_fsock',
				'value' => '1',
				'type' => 'yesno',
				'cat' => 'a',
				'order' => 1
			);
		}
		if(isset($settings)) $db->insert_query_multiple('mysubs_settings', $settings);
	}
	
	function mysubs_is_installed()
	{
		global $db;
		
		return $db->table_exists('mysubs');
	}
	
	function mysubs_uninstall()
	{
		global $cache, $db;
		
		/* Drop the subscriptions table. */
		if($db->table_exists('mysubs')) $db->drop_table('mysubs');
		
		/* Drop the IPN log table. */
		if($db->table_exists('mysubs_notifs')) $db->drop_table('mysubs_notifs');
		
		/* Delete the settings. */
		if($db->table_exists('mysubs_settings')) $db->drop_table('mysubs_settings');
		
	}
	
	function mysubs_activate()
	{
		global $cache, $db;
		
		/* Create new task to check updates to users. */
		if(!$db->fetch_array($db->simple_select('tasks', '*', "`file`='mysubs'")))
		{
			$new_task = array(
				"title" => 'MySubscriptions Updates',
				"description" => 'Checks for expired subscriptions and updates administration of new subscriptions if set.',
				"file" => 'mysubs',
				"minute" => '30,59',
				"hour" => '*',
				"day" => '*',
				"month" => '*',
				"weekday" => '*',
				"enabled" => 1,
				"logging" => 1,
				"locked" => 0
			);
			require_once MYBB_ROOT."inc/functions_task.php";
			
			$new_task['nextrun'] = fetch_next_run($new_task);
			$tid = $db->insert_query("tasks", $new_task);
			$cache->update_tasks();
		}
	}
	
	function mysubs_deactivate()
	{
		global $cache, $db;
	
		/* Remove the task. */
		if($db->fetch_array($db->simple_select('tasks', '*', "`file` LIKE 'mysubs'"))) $db->delete_query('tasks', "`file` LIKE 'mysubs'");
		$cache->update_tasks();
	}
	
	function mysubs_menu(&$sub_menu)
	{
		global $mybb, $lang;

		end($sub_menu);
		$key = (key($sub_menu))+10;

		if(!$key) $key = '50';
		$sub_menu[$key] = array('id' => 'mysubs', 'title' => 'MySubscriptions', 'link' => "index.php?module=user-mysubs");
	}
	
	function mysubs_action_handler(&$action)
	{
		$action['mysubs'] = array('active' => 'mysubs', 'file' => 'mysubs.php');
	}
	
	function mysubs_payment_page()
	{
		global $db, $mybb, $header, $headerinclude, $footer, $theme, $lang;
		
		$lang->load('mysubs');

		$settings = ipn_settings();
		
		if(!$settings['enabled']) redirect('index.php', $lang->error_subscriptions_disabled);
		
		if($mybb->input['action'] == 'payments')
		{
			if(!$mybb->user['uid']) error_no_permission();
			add_breadcrumb($lang->payments, "misc.php?action=payments");
			$contents = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xml:lang="en" lang="en" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>'.$mybb->settings['bbname'].' - Payments</title>
		'.$headerinclude.'
	</head>
	<body>'.$header;
			if($mybb->input['price_option'] && $mybb->input['my_post_key'] == $mybb->post_code)
			{
				$sub = array();
				$query = $db->simple_select('mysubs');
				while($item = $db->fetch_array($query)) if($mybb->input['item_'.$item['sid']]) $sub = $item;
				if(empty($sub)) redirect('misc.php?action=payments', $lang->error_invalid_subscription);
				if($mybb->input['price_option'][$sub['sid']] == '') redirect('misc.php?action=payments', $lang->error_invalid_length);
				if($sub['accepted_gids'] != 'all')
				{
					$gids = explode(',', $mybb->user['additionalgroups']);
					$gids[] = $mybb->user['usergroup'];
					$matched = array_intersect($gids, explode(',', $sub['accepted_gids']));
					if(empty($matched)) redirect('misc.php?action=payments', $lang->error_invalid_gid);
				}
				
				add_breadcrumb($sub['name'], "misc.php?action=payments");
				
				$setting = ipn_settings();
				
				$prices = unserialize($sub['price']);
				$item_price = $prices[$mybb->input['price_option'][$sub['sid']]]['c'];
				$price = $item_price.' '.$sub['currency'];
				$dur = $prices[$mybb->input['price_option'][$sub['sid']]]['l'];
				if($dur > 0)
				{
					$dur .= ' ';
					switch($prices[$mybb->input['price_option'][$sub['sid']]]['lt'])
					{
						case 'd':$dur .= (intval($prices[$mybb->input['price_option'][$sub['sid']]]['l']) > 1) ? $lang->days : $lang->day; break;
						case 'm': $dur .= (intval($prices[$mybb->input['price_option'][$sub['sid']]]['l']) > 1) ? $lang->months : $lang->month; break;
						case 'y': $dur .= (intval($prices[$mybb->input['price_option'][$sub['sid']]]['l']) > 1) ? $lang->years : $lang->year; break;
					}
				}
				else $dur = $lang->permanent;
				switch($sub['currency'])
				{
					case 'EUR':
						$curcode = '&euro;';
						break;
					case 'USD':
					case 'CAD':
					default:
						$curcode = '$';
						break;
				}
				$ipn_url = $mybb->settings['bburl'].'/misc.php?do=paypal_ipn';
				
				$custom = base64_encode(serialize(array('uid' => $mybb->user['uid'], 'po' => $mybb->input['price_option'][$sub['sid']])));

				$contents .= <<<EOF
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
				<tr>
					<td class="thead" align="center"><strong>{$lang->payments}</strong></td>
				</tr>
				<tr>
					<td class="trow1" valign="bottom">
						<center>
							{$lang->confirm_details}<br /><br />
							<span style="font-weight: bold;">{$sub['name']}</span> - {$curcode}{$price} ({$dur})<br /><br />
							<input type="hidden" name="cmd" value="_xclick" />
							<input type="hidden" name="business" value="{$setting['pp_email']}" />
							<input type="hidden" name="item_name" value="{$sub['item_name']}" />
							<input type="hidden" name="item_number" value="{$sub['item_number']}" />
							<input type="hidden" name="currency_code" value="{$sub['currency']}" />
							<input type="hidden" name="amount" value="{$item_price}" />
							<input type="hidden" name="no_shipping" value="1" />
							<input type="hidden" name="shipping" value="0.00" />
							<input type="hidden" name="return" value="{$mybb->settings['bburl']}" />
							<input type="hidden" name="cancel_return" value="{$mybb->settings['bburl']}" />
							<input type="hidden" name="notify_url" value="{$ipn_url}" />
							<input type="hidden" name="custom" value="{$custom}" />
							<input type="hidden" name="no_note" value="1" />
							<input type="hidden" name="tax" value="0.00" />
							<input type="submit" name="submit" value="{$lang->confirm_purchase}" />
						</center>
					</td>
				</tr>
			</table>
		</form>
EOF;
			}
			else
			{
				$query = $db->simple_select('mysubs');
				while($item = $db->fetch_array($query))
				{
					switch($item['currency'])
					{
						case 'EUR':
							$curcode = '&euro;';
							break;
						case 'USD':
						case 'CAD':
						default:
							$curcode = '$';
							break;
					}
					$options = '';
					$price_options = unserialize($item['price']);
					foreach($price_options as $key => $po)
					{
						if(intval($po['l']) == 0) $dur = 'Permanent';
						else
						{
							$dur = $po['l'].' ';
							switch($po['lt'])
							{
								case 'd':$dur .= (intval($po['l']) > 1) ? $lang->days : $lang->day; break;
								case 'm': $dur .= (intval($po['l']) > 1) ? $lang->months : $lang->month; break;
								case 'y': $dur .= (intval($po['l']) > 1) ? $lang->years : $lang->year; break;
							}
						}
						$options .= '
									<optgroup label="'.$dur.'">
										<option value="'.$key.'">'.$curcode.$po['c'].' '.$item['currency'].'</option>
									</optgroup>';
					}
					$payments .= '
						<fieldset>
							<legend><strong>'.$item['name'].'</strong></legend>
							<div class="smalltext" style="width: 65%;">
								'.$item['description'].'
							</div>
							<div align="right" style="margin-right: 100px;">
								<select style="width: 120px;" name="price_option['.$item['sid'].']">
									<option value="">-----</option>'.$options.'
								</select> <input type="submit" name="item_'.$item['sid'].'" value="Order" class="button" />
							</div>
						</fieldset>';
				}
				if(empty($payments)) $payments = '<p style="text-align: center;">'.$lang->no_subscription_options.'</p>';
				$contents .= <<<EOF
		<form action="misc.php?action=payments" method="post">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
				<tr>
					<td class="thead" align="center"><strong>Payments</strong></td>
				</tr>
				<tr>
					<td class="trow1" valign="bottom">{$payments}
					</td>
				</tr>
			</table>
		</form>
EOF;
			}
			$contents .= $footer.'
	</body>
</html>';
output_page($contents);
		}
	}
	
	function mysubs_ipn_handler()
	{
		global $db, $mybb;
		
		if($mybb->input['do'] == 'paypal_ipn')
		{
			$settings = ipn_settings();
			
			$temp_ipn_data = array();
			foreach($_POST as $key => $value)
			{
				$value = urlencode(stripslashes($value));
				$settings['req'] .= "&".$key."=".$value;
				$settings['ipn_email'] .= $key." = ".urldecode($value).'<br />';
				$temp_ipn_data[$key] = urldecode($value);
			}
			$ipn_serialized = serialize($temp_ipn_data);
			$ipn_data = ipn_data();
			
			if(!$settings['enabled']) handle_unknown($ipn_data, 'IPN_DISABLED', $ipn_serialized);
			else
			{
				if(ipn_validate($settings))
				{
					$txn_type = strtoupper($ipn_data['txn_type']);
					$payment_status = strtoupper($ipn_data['payment_status']);
					$reason_code = strtoupper($ipn_data['reason_code']);
					
					if(
						$txn_type == 'NEW_CASE' || 
						$payment_status == 'REVERSED' || 
						$payment_status == 'CANCELED_REVERSAL' || 
						$txn_type == 'ADJUSTMENT'
					)
						handle_dispute($ipn_data, $txn_type, $payment_status, $ipn_serialized);
					
					else if($reason_code == 'REFUND')
						handle_refund($ipn_data, $ipn_serialized);
					
					else if($reason_code != 'REFUND' && 
						(
							$txn_type == 'CART' || 
							$txn_type == 'EXPRESS_CHECKOUT' || 
							$txn_type == 'VIRTUAL_TERMINAL' || 
							$txn_type == 'WEB_ACCEPT' || 
							$txn_type == 'SEND_MONEY'
						)
					)
						handle_order($ipn_data, $txn_type, $ipn_serialized);
					
					else if(
						$txn_type == 'SUBSCR_SIGNUP' || 
						$txn_type == 'SUBSCR_FAILED' || 
						$txn_type == 'SUBSCR_CANCEL' || 
						$txn_type == 'SUBSCR_EOT' || 
						$txn_type == 'SUBSCR_MODIFY' || 
						$txn_type == 'SUBSCR_PAYMENT'
					)
						handle_subscription($ipn_data, $txn_type, $ipn_serialized);
					/*
						// Using subscriptions for now, so this is uneeded.
					
						else if(
							$txn_type == 'RECURRING_PAYMENT_PROFILE_CREATED' || 
							$txn_type == 'RECURRING_PAYMENT' || 
							$txn_type == 'RECURRING_PAYMENT_EXPIRED' ||
							$txn_type == 'RECURRING_PAYMENT_SKIPPED'
						)
							handle_recurring($ipn_data, $txn_type);
							
						// Also not used for now.
						else if(
							$txn_type == 'MASSPAY'
						)
							handle_masspay($ipn_data);
					*/
					else handle_unknown($ipn_data, $txn_type, $ipn_serialized);
				}
				else
				{
					$insert_data = array(
						'uid' => 0,
						'item_number' => $db->escape_string($ipn_data['item_number']),
						'expiration' => 0,
						'email' => $db->escape_string($ipn_data['payer_email']),
						'time' => TIME_NOW,
						'success' => 0,
						'active' => 0,
						'txn_type' =>'VERIFICATION_FAILED',
						'ipn_data' => $db->escape_string($ipn_serialized)
					);
					$db->insert_query('mysubs_notifs', $insert_data);
				}
			}
		}
	}
	
	function handle_dispute($ipn_data, $txn_type, $payment_status, $ipn_serialized)
	{
		/*
			$txn_type
				NEW_CASE	- New disputed filed.
				ADJUSTMENT	- Disputed resolved & closed.
				
			$payment_status
				REVERSED
					A payment was reversed due to a chargeback or other type of reversal. 
					The funds have been removed from your account balance and returned to the buyer. 
					The reason for the reversal is specified in the ReasonCode element.
				CANCELED_REVERSAL
					A reversal has been canceled. For example, you won a dispute with the customer, 
					and the funds for the transaction that was reversed have been returned to you.
		*/
		global $db;
		
		$custom_data = @unserialize($ipn_data['custom']);
		$insert_data = array(
			'uid' => intval(@$custom_data['uid']),
			'item_number' => $db->escape_string($ipn_data['item_number']),
			'expiration' => 0,
			'email' => $db->escape_string($ipn_data['payer_email']),
			'time' => TIME_NOW,
			'success' => 0,
			'active' => 0,
			'txn_type' => $db->escape_string($ipn_data['txn_type']),
			'ipn_data' => $db->escape_string($ipn_serialized)
		);
		$db->insert_query('mysubs_notifs', $insert_data);
	}

	function handle_refund($ipn_data, $ipn_serialized)
	{
		// A reversal has occurred on this transaction because you have given the customer a refund.
		global $db;
	}

	function handle_order($ipn_data, $txn_type, $ipn_serialized)
	{
		/*
			$txn_type
				CART
					Payment received for multiple items; source is Express Checkout or the PayPal Shopping Cart.
				EXPRESS_CHECKOUT
					Payment received for a single item; source is Express Checkout.
				VIRTUAL_TERMINAL
					Payment received; source is Virtual Terminal.
				WEB_ACCEPT
					Payment received; source is a Buy Now, Donation, or Auction Smart Logos button.
				SEND_MONEY
					Payment received; source is the Send Money tab on the PayPal website.
		*/
		global $db;
		
		if($txn_type == 'WEB_ACCEPT') // This is the txn type we want (for now). Any other type should be considered unhandled.
		{
			$item_number = $db->escape_string($ipn_data['item_number']);
			$query = $db->simple_select('mysubs', '*', "`item_number`='{$item_number}'");
			if($db->num_rows($query) > 0)
			{
				$item = $db->fetch_array($query);
				$price = unserialize($item['price']);
				$custom = unserialize(base64_decode($ipn_data['custom']));
				$uid = intval($custom['uid']);
				$user = get_user($uid);
				if($user)
				{
					$gid = intval($user['usergroup']);
					if($price[$custom['po']]['c'] == $ipn_data['mc_gross'])
					{
						$accepted_gid = true;
						if($sub['accepted_gids'] != 'all')
						{
							$gids = explode(',', $user['additionalgroups']);
							$gids[] = $user['usergroup'];
							$matched = array_intersect($gids, explode(',', $sub['accepted_gids']));
							if(empty($matched)) $accepted_gid = false;
						}
						if($accepted_gid)
						{
							$base_time = TIME_NOW;
							if($gid == intval($item['new_group']))
							{
								$notif_result = $db->simple_select('mysubs_notifs', '*', "`uid` = {$user['uid']} AND `active` = 1", array('limit' => 1));
								if($notif_result && $db->num_rows($notif_result) > 0)
								{
									$notif = $db->fetch_array($notif_result);
									$base_time = intval($notif['expiration']);
									$gid = $notif['old_gid'];
								}
							}
							$update_data = array(
								'usergroup' => intval($item['new_group'])
							);
							$result = $db->update_query('users', $update_data, "`uid`=$uid");
							if($result)
							{
								$db->update_query('mysubs_notifs', array('active' => 0), "`uid` = {$user['uid']}");
								if(intval($price[$custom['po']]['l']) < 1) $expiration_time = 0;
								else
								{
									$lt = 1;
									switch($price[$custom['po']]['lt'])
									{
										case 'm':
											$lt = 30;
										break;
										
										case 'y':
											$lt = 365;
										break;
										
										case 'd':
										default:
										break;
									}
									$expiration_time = intval($base_time + (60 * 60 * 24 * $lt * intval($price[$custom['po']]['l'])));
								}
								$insert_data = array(
									'uid' => intval($custom['uid']),
									'item_number' => $db->escape_string($ipn_data['item_number']),
									'expiration' => $expiration_time,
									'email' => $db->escape_string($ipn_data['payer_email']),
									'time' => time(),
									'success' => 1,
									'active' => 1,
									'old_gid' => $gid,
									'new_gid' => intval($item['new_group']),
									'txn_type' => $db->escape_string($ipn_data['txn_type']),
									'ipn_data' => $db->escape_string($ipn_serialized)
								);
								$db->insert_query('mysubs_notifs', $insert_data);
							}
							else
							{
								// For some reason the user's group was not updated properly.
								handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'ERROR_UPDATING_USERGROUP');
							}
						}
						else
						{
							// User not allowed to purchase this item.
							handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'USERGROUP_FORBIDDEN');
						}
					}
					else
					{
						// Incorrect price, log this.
						handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'INCORRECT_GROSS');
					}
				}
				else
				{
					// User doesn't exist.
					handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'UNKNOWN_USER');
				}
			}
			else
			{
				// Item doesn't exist in our records.
				handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'ITEM_NOT_FOUND');
			}
		}
		else
		{
			// This isn't currently a supported transaction type.
			handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'TXN_NOT_SUPPORTED');
		}
	}
	
	function handle_subscription($ipn_data, $txn_type, $ipn_serialized)
	{
		handle_unknown($ipn_data, $txn_type, $ipn_serialized, 'TXN_NOT_SUPPORTED');
	}

	function handle_unknown($ipn_data, $txn_type, $ipn_serialized, $log='UNHANDLED')
	{
		// This handles all other types of transactions.
		global $db;
		
		$custom_data = @unserialize($ipn_data['custom']);
		$insert_data = array(
			'uid' => intval(@$custom_data['uid']),
			'item_number' => $db->escape_string($ipn_data['item_number']),
			'expiration' => 0,
			'email' => $db->escape_string($ipn_data['payer_email']),
			'time' => TIME_NOW,
			'success' => 0,
			'log' => $db->escape_string($log),
			'active' => 0,
			'txn_type' => $db->escape_string($ipn_data['txn_type']),
			'ipn_data' => $db->escape_string($ipn_serialized)
		);
		$db->insert_query('mysubs_notifs', $insert_data);
	}
	
	#####################################
	#									#
	#			 IPN Settings			#
	#									#
	#####################################
	function ipn_data()
	{
		// Buyer Information
		$ipn_array_data['address_city'] = isset($_POST['address_city']) ? $_POST['address_city'] : '';
		$ipn_array_data['address_country'] = isset($_POST['address_country']) ? $_POST['address_country'] : '';
		$ipn_array_data['address_country_code'] = isset($_POST['address_country_code']) ? $_POST['address_country_code'] : '';
		$ipn_array_data['address_name'] = isset($_POST['address_name']) ? $_POST['address_name'] : '';
		$ipn_array_data['address_state'] = isset($_POST['address_state']) ? $_POST['address_state'] : '';
		$ipn_array_data['address_status'] = isset($_POST['address_status']) ? $_POST['address_status'] : '';
		$ipn_array_data['address_street'] = isset($_POST['address_street']) ? $_POST['address_street'] : '';
		$ipn_array_data['address_zip'] = isset($_POST['address_zip']) ? $_POST['address_zip'] : '';
		$ipn_array_data['first_name'] = isset($_POST['first_name']) ? $_POST['first_name'] : '';
		$ipn_array_data['last_name'] = isset($_POST['last_name']) ? $_POST['last_name'] : '';
		$ipn_array_data['payer_business_name'] = isset($_POST['payer_business_name']) ? $_POST['payer_business_name'] : '';
		$ipn_array_data['payer_email'] = isset($_POST['payer_email']) ? $_POST['payer_email'] : '';
		$ipn_array_data['payer_id'] = isset($_POST['payer_id']) ? $_POST['payer_id'] : '';
		$ipn_array_data['payer_status'] = isset($_POST['payer_status']) ? $_POST['payer_status'] : '';
		$ipn_array_data['contact_phone'] = isset($_POST['contact_phone']) ? $_POST['contact_phone'] : '';
		$ipn_array_data['residence_country'] = isset($_POST['residence_country']) ? $_POST['residence_country'] : '';
		
		// Basic Information
		$ipn_array_data['notify_version'] = isset($_POST['notify_version']) ? $_POST['notify_version'] : ''; 
		$ipn_array_data['verify_sign'] = isset($_POST['verify_sign']) ? $_POST['verify_sign'] : '';
		$ipn_array_data['charset'] = isset($_POST['charset']) ? $_POST['charset'] : '';
		$ipn_array_data['btn_id'] = isset($_POST['btn_id']) ? $_POST['btn_id'] : '';
		$ipn_array_data['business'] = isset($_POST['business']) ? $_POST['business'] : '';
		$ipn_array_data['item_name'] = isset($_POST['item_name']) ? $_POST['item_name'] : '';
		$ipn_array_data['item_number'] = isset($_POST['item_number']) ? $_POST['item_number'] : '';
		$ipn_array_data['quantity'] = isset($_POST['quantity']) ? $_POST['quantity'] : 0;
		$ipn_array_data['receiver_email'] = isset($_POST['receiver_email']) ? $_POST['receiver_email'] : '';
		$ipn_array_data['receiver_id'] = isset($_POST['receiver_id']) ? $_POST['receiver_id'] : '';
		$ipn_array_data['transaction_subject'] = isset($_POST['transaction_subject']) ? $_POST['transaction_subject'] : '';
		
		// Cart Items
		$ipn_array_data['num_cart_items'] = isset($_POST['num_cart_items']) ? $_POST['num_cart_items'] : '';

		$i = 1;
		$ipn_array_data['cart_items'] = array();
		while(isset($_POST['item_number' . $i]))
		{
			$ipn_array_data['item_number'] = isset($_POST['item_number' . $i]) ? $_POST['item_number' . $i] : '';
			$ipn_array_data['item_name'] = isset($_POST['item_name' . $i]) ? $_POST['item_name' . $i] : '';
			$ipn_array_data['quantity'] = isset($_POST['quantity' . $i]) ? $_POST['quantity' . $i] : '';
			$ipn_array_data['mc_gross'] = isset($_POST['mc_gross_' . $i]) ? $_POST['mc_gross_' . $i] : 0;
			$ipn_array_data['mc_handling'] = isset($_POST['mc_handling' . $i]) ? $_POST['mc_handling' . $i] : 0;
			$ipn_array_data['mc_shipping'] = isset($_POST['mc_shipping' . $i]) ? $_POST['mc_shipping' . $i] : 0;
			$ipn_array_data['custom'] = isset($_POST['custom' . $i]) ? $_POST['custom' . $i] : '';
			$ipn_array_data['option_name1'] = isset($_POST['option_name1_' . $i]) ? $_POST['option_name1_' . $i] : '';
			$ipn_array_data['option_selection1'] = isset($_POST['option_selection1_' . $i]) ? $_POST['option_selection1_' . $i] : '';
			$ipn_array_data['option_name2'] = isset($_POST['option_name2_' . $i]) ? $_POST['option_name2_' . $i] : '';
			$ipn_array_data['option_selection2'] = isset($_POST['option_selection2_' . $i]) ? $_POST['option_selection2_' . $i] : '';
			$ipn_array_data['option_name3'] = isset($_POST['option_name3_' . $i]) ? $_POST['option_name3_' . $i] : '';
			$ipn_array_data['option_selection3'] = isset($_POST['option_selection3_' . $i]) ? $_POST['option_selection3_' . $i] : '';
			$ipn_array_data['option_name4'] = isset($_POST['option_name4_' . $i]) ? $_POST['option_name4_' . $i] : '';
			$ipn_array_data['option_selection4'] = isset($_POST['option_selection4_' . $i]) ? $_POST['option_selection4_' . $i] : '';
			$ipn_array_data['option_name5'] = isset($_POST['option_name5_' . $i]) ? $_POST['option_name5_' . $i] : '';
			$ipn_array_data['option_selection5'] = isset($_POST['option_selection5_' . $i]) ? $_POST['option_selection5_' . $i] : '';
			$ipn_array_data['option_name6'] = isset($_POST['option_name6_' . $i]) ? $_POST['option_name6_' . $i] : '';
			$ipn_array_data['option_selection6'] = isset($_POST['option_selection6_' . $i]) ? $_POST['option_selection6_' . $i] : '';
			$ipn_array_data['option_name7'] = isset($_POST['option_name7_' . $i]) ? $_POST['option_name7_' . $i] : '';
			$ipn_array_data['option_selection7'] = isset($_POST['option_selection7_' . $i]) ? $_POST['option_selection7_' . $i] : '';
			$ipn_array_data['option_name8'] = isset($_POST['option_name8_' . $i]) ? $_POST['option_name8_' . $i] : '';
			$ipn_array_data['option_selection8'] = isset($_POST['option_selection8_' . $i]) ? $_POST['option_selection8_' . $i] : '';
			$ipn_array_data['option_name9'] = isset($_POST['option_name9_' . $i]) ? $_POST['option_name9_' . $i] : '';
			$ipn_array_data['option_selection9'] = isset($_POST['option_selection9_' . $i]) ? $_POST['option_selection9_' . $i] : '';
			
			$ipn_array_data['btn_id'] = isset($_POST['btn_id' . $i]) ? $_POST['btn_id' . $i] : '';
			
			$ipn_array_data['current_item'] = array(
				'item_number' => $ipn_array_data['item_number'],
				'item_name' => $ipn_array_data['item_name'],
				'quantity' => $ipn_array_data['quantity'], 
				'mc_gross' => $ipn_array_data['mc_gross'], 
				'mc_handling' => $ipn_array_data['mc_handling'], 
				'mc_shipping' => $ipn_array_data['mc_shipping'], 
				'custom' => $ipn_array_data['custom'],
				'option_name1' => $ipn_array_data['option_name1'],
				'option_selection1' => $ipn_array_data['option_selection1'],
				'option_name2' => $ipn_array_data['option_name2'],
				'option_selection2' => $ipn_array_data['option_selection2'], 
				'option_name3' => $ipn_array_data['option_name3'], 
				'option_selection3' => $ipn_array_data['option_selection3'], 
				'option_name4' => $ipn_array_data['option_name4'], 
				'option_selection4' => $ipn_array_data['option_selection4'], 
				'option_name5' => $ipn_array_data['option_name5'], 
				'option_selection5' => $ipn_array_data['option_selection5'], 
				'option_name6' => $ipn_array_data['option_name6'], 
				'option_selection6' => $ipn_array_data['option_selection6'], 
				'option_name7' => $ipn_array_data['option_name7'], 
				'option_selection7' => $ipn_array_data['option_selection7'], 
				'option_name8' => $ipn_array_data['option_name8'], 
				'option_selection8' => $ipn_array_data['option_selection8'], 
				'option_name9' => $ipn_array_data['option_name9'], 
				'option_selection9' => $ipn_array_data['option_selection9'], 
				'btn_id' => $ipn_array_data['btn_id']
			);
				 
			array_push($ipn_array_data['cart_items'], $ipn_array_data['current_item']);
			$i++;
		}
		
		// Advanced and Custom Information
		$ipn_array_data['custom'] = isset($_POST['custom']) ? $_POST['custom'] : '';
		$ipn_array_data['invoice'] = isset($_POST['invoice']) ? $_POST['invoice'] : '';
		$ipn_array_data['memo'] = isset($_POST['memo']) ? $_POST['memo'] : '';
		$ipn_array_data['option_name1'] = isset($_POST['option_name1']) ? $_POST['option_name1'] : '';
		$ipn_array_data['option_selection1'] = isset($_POST['option_selection1']) ? $_POST['option_selection1'] : '';
		$ipn_array_data['option_name2'] = isset($_POST['option_name2']) ? $_POST['option_name2'] : '';
		$ipn_array_data['option_selection2'] = isset($_POST['option_selection2']) ? $_POST['option_selection2'] : '';
		$ipn_array_data['option_name3'] = isset($_POST['option_name3']) ? $_POST['option_name3'] : '';
		$ipn_array_data['option_selection3'] = isset($_POST['option_selection3']) ? $_POST['option_selection3'] : '';
		$ipn_array_data['option_name4'] = isset($_POST['option_name4']) ? $_POST['option_name4'] : '';
		$ipn_array_data['option_selection4'] = isset($_POST['option_selection2']) ? $_POST['option_selection4'] : '';
		$ipn_array_data['option_name5'] = isset($_POST['option_name5']) ? $_POST['option_name5'] : '';
		$ipn_array_data['option_selection5'] = isset($_POST['option_selection2']) ? $_POST['option_selection5'] : '';
		$ipn_array_data['option_name6'] = isset($_POST['option_name6']) ? $_POST['option_name6'] : '';
		$ipn_array_data['option_selection6'] = isset($_POST['option_selection2']) ? $_POST['option_selection6'] : '';
		$ipn_array_data['option_name7'] = isset($_POST['option_name7']) ? $_POST['option_name7'] : '';
		$ipn_array_data['option_selection7'] = isset($_POST['option_selection2']) ? $_POST['option_selection7'] : '';
		$ipn_array_data['option_name8'] = isset($_POST['option_name8']) ? $_POST['option_name8'] : '';
		$ipn_array_data['option_selection8'] = isset($_POST['option_selection2']) ? $_POST['option_selection8'] : '';
		$ipn_array_data['option_name9'] = isset($_POST['option_name9']) ? $_POST['option_name9'] : '';
		$ipn_array_data['option_selection9'] = isset($_POST['option_selection2']) ? $_POST['option_selection9'] : '';
		
		// Website Payments Standard, Website Payments Pro, and Refund Information
		$ipn_array_data['auth_id'] = isset($_POST['auth_id']) ? $_POST['auth_id'] : '';
		$ipn_array_data['auth_exp'] = isset($_POST['auth_exp']) ? $_POST['auth_exp'] : '';
		$ipn_array_data['auth_amount'] = isset($_POST['auth_amount']) ? $_POST['auth_amount'] : '';
		$ipn_array_data['auth_status'] = isset($_POST['auth_status']) ? $_POST['auth_status'] : '';
		
		// Fraud Management Filters
		$i = 1;
		$ipn_array_data['fraud_management_filters'] = array();
		while(isset($_POST['fraud_management_filters_' . $i]))
		{
			$ipn_array_data['filter_name'] = isset($_POST['fraud_management_filter_' . $i]) ? $_POST['fraud_management_filter_' . $i] : '';
					 
			array_push($ipn_array_data['fraud_management_filters'], $ipn_array_data['filter_name']);
			$i++;
		}
		
		$ipn_array_data['mc_gross'] = isset($_POST['mc_gross']) ? $_POST['mc_gross'] : 0;
		$ipn_array_data['mc_handling'] = isset($_POST['mc_handling']) ? $_POST['mc_handling'] : 0;
		$ipn_array_data['mc_shipping'] = isset($_POST['mc_shipping']) ? $_POST['mc_shipping'] : 0;
		$ipn_array_data['mc_fee'] = isset($_POST['mc_fee']) ? $_POST['mc_fee'] : 0;
		$ipn_array_data['num_cart_items'] = isset($_POST['num_cart_items']) ? $_POST['num_cart_items'] : 0;
		$ipn_array_data['parent_txn_id'] = isset($_POST['parent_txn_id']) ? $_POST['parent_txn_id'] : '';
		$ipn_array_data['payment_date'] = isset($_POST['payment_date']) ? $_POST['payment_date'] : '';
		$ipn_array_data['payment_status'] = isset($_POST['payment_status']) ? $_POST['payment_status'] : '';
		$ipn_array_data['payment_type'] = isset($_POST['payment_type']) ? $_POST['payment_type'] : '';
		$ipn_array_data['pending_reason'] = isset($_POST['pending_reason']) ? $_POST['pending_reason'] : '';
		$ipn_array_data['protection_eligibility'] = isset($_POST['protection_eligibility']) ? $_POST['protection_eligibility'] : '';
		$ipn_array_data['reason_code'] = isset($_POST['reason_code']) ? $_POST['reason_code'] : '';
		$ipn_array_data['remaining_settle'] = isset($_POST['remaining_settle']) ? $_POST['remaining_settle'] : '';
		$ipn_array_data['shipping_method'] = isset($_POST['shipping_method']) ? $_POST['shipping_method'] : '';
		$ipn_array_data['shipping'] = isset($_POST['shipping']) ? $_POST['shipping'] : 0;
		$ipn_array_data['tax'] = isset($_POST['tax']) ? $_POST['tax'] : 0;
		$ipn_array_data['transaction_entity'] = isset($_POST['transaction_entity']) ? $_POST['transaction_entity'] : '';
		$ipn_array_data['txn_id'] = isset($_POST['txn_id']) ? $_POST['txn_id'] : '';
		$ipn_array_data['txn_type'] = isset($_POST['txn_type']) ? $_POST['txn_type'] : '';

		// Currency and Currency Exchange Information
		$ipn_array_data['exchange_rate'] = isset($_POST['exchange_rate']) ? $_POST['exchange_rate'] : '';
		$ipn_array_data['mc_currency'] = isset($_POST['mc_currency']) ? $_POST['mc_currency'] : '';
		$ipn_array_data['settle_amount'] = isset($_POST['settle_amount']) ? $_POST['settle_amount'] : 0;
		$ipn_array_data['settle_currency'] = isset($_POST['settle_currency']) ? $_POST['settle_currency'] : '';
		
		// Auction Variables
		$ipn_array_data['auction_buyer_id'] = isset($_POST['auction_buyer_id']) ? $_POST['auction_buyer_id'] : '';
		$ipn_array_data['auction_closing_date'] = isset($_POST['auction_closing_date']) ? $_POST['auction_closing_date'] : '';
		$ipn_array_data['auction_multi_item'] = isset($_POST['auction_multi_item']) ? $_POST['auction_multi_item'] : 0;
		$ipn_array_data['for_auction'] = isset($_POST['for_auction']) ? 1 : 0; 
		$ipn_array_data['handling_amount'] = isset($_POST['handling_amount']) ? $_POST['handling_amount'] : 0;
		$ipn_array_data['shipping_discount'] = isset($_POST['shipping_discount']) ? $_POST['shipping_discount'] : 0;
		$ipn_array_data['insurance_amount'] = isset($_POST['insurance_amount']) ? $_POST['insurance_amount'] : 0;
		
		// Mass Payments
		$i = 1;
		$ipn_array_data['mass_payments'] = array();
		while(isset($_POST['masspay_txn_id_' . $i]))
		{
			$ipn_array_data['masspay_txn_id'] = isset($_POST['masspay_txn_id_' . $i]) ? $_POST['masspay_txn_id_' . $i] : '';
			$ipn_array_data['mc_currency'] = isset($_POST['mc_currency_' . $i]) ? $_POST['mc_currency_' . $i] : '';
			$ipn_array_data['mc_fee'] = isset($_POST['mc_fee_' . $i]) ? $_POST['mc_fee_' . $i] : 0;
			$ipn_array_data['mc_gross'] = isset($_POST['mc_gross_' . $i]) ? $_POST['mc_gross_' . $i] : 0;
			$ipn_array_data['receiver_email'] = isset($_POST['receiver_email_' . $i]) ? $_POST['receiver_email_' . $i] : '';
			$ipn_array_data['status'] = isset($_POST['status_' . $i]) ? $_POST['status_' . $i] : '';
			$ipn_array_data['unique_id'] = isset($_POST['unique_id_' . $i]) ? $_POST['unique_id_' . $i] : '';
			$ipn_array_data['current_payment_data_set'] = array(
				'masspay_txn_id' => $ipn_array_data['masspay_txn_id'],
				'mc_currency' => $ipn_array_data['mc_currency'],
				'mc_fee' => $ipn_array_data['mc_fee'],
				'mc_gross' => $ipn_array_data['mc_gross'],
				'receiver_email' => $ipn_array_data['receiver_email'],
				'status' => $ipn_array_data['status'],
				'unique_id' => $ipn_array_data['unique_id']
			);
			array_push($ipn_array_data['mass_payments'], $ipn_array_data['current_payment_data_set']);
			$i++;
		}

		// Recurring Payments Information
		$ipn_array_data['initial_payment_status'] = isset($_POST['initial_payment_status']) ? $_POST['initial_payment_status'] : '';
		$ipn_array_data['initial_payment_txn_id'] = isset($_POST['initial_payment_txn_id']) ? $_POST['initial_payment_txn_id'] : '';
		$ipn_array_data['recurring_payment_id'] = isset($_POST['recurring_payment_id']) ? $_POST['recurring_payment_id'] : '';
		$ipn_array_data['product_name'] = isset($_POST['product_name']) ? $_POST['product_name'] : '';
		$ipn_array_data['product_type'] = isset($_POST['product_type']) ? $_POST['product_type'] : '';
		$ipn_array_data['period_type'] = isset($_POST['period_type']) ? $_POST['period_type'] : '';
		$ipn_array_data['payment_cycle'] = isset($_POST['payment_cycle']) ? $_POST['payment_cycle'] : '';
		$ipn_array_data['outstanding_balance'] = isset($_POST['outstanding_balance']) ? $_POST['outstanding_balance'] : '';
		$ipn_array_data['amount_per_cycle'] = isset($_POST['amount_per_cycle']) ? $_POST['amount_per_cycle'] : 0;
		$ipn_array_data['initial_payment_amount'] = isset($_POST['initial_payment_amount']) ? $_POST['initial_payment_amount'] : '';
		$ipn_array_data['profile_status'] = isset($_POST['profile_status']) ? $_POST['profile_status'] : '';
		$ipn_array_data['amount'] = isset($_POST['amount']) ? $_POST['amount'] : '';
		$ipn_array_data['time_created'] = isset($_POST['time_created']) ? $_POST['time_created'] : '';
		$ipn_array_data['next_payment_date'] = isset($_POST['next_payment_date']) ? $_POST['next_payment_date'] : ''; 
		$ipn_array_data['rp_invoice_id'] = isset($_POST['rp_invoice_id']) ? $_POST['rp_invoice_id'] : '';
						 
		// Subscription Variables
		$ipn_array_data['subscr_date'] = isset($_POST['subscr_date']) ? $_POST['subscr_date'] : '';
		$ipn_array_data['subscr_effective'] = isset($_POST['subscr_effective']) ? $_POST['subscr_effective'] : '';
		$ipn_array_data['period1'] = isset($_POST['period1']) ? $_POST['period1'] : '';
		$ipn_array_data['period2'] = isset($_POST['period2']) ? $_POST['period2'] : '';
		$ipn_array_data['period3'] = isset($_POST['period3']) ? $_POST['period3'] : '';
		$ipn_array_data['amount1'] = isset($_POST['amount1']) ? $_POST['amount1'] : 0;
		$ipn_array_data['amount2'] = isset($_POST['amount2']) ? $_POST['amount2'] : 0;
		$ipn_array_data['amount3'] = isset($_POST['amount3']) ? $_POST['amount3'] : 0;
		$ipn_array_data['mc_amount1'] = isset($_POST['mc_amount1']) ? $_POST['mc_amount1'] : 0;
		$ipn_array_data['mc_amount2'] = isset($_POST['mc_amount2']) ? $_POST['mc_amount2'] : 0;
		$ipn_array_data['mc_amount3'] = isset($_POST['mc_amount3']) ? $_POST['mc_amount3'] : 0;
		$ipn_array_data['mc_currency'] = isset($_POST['mc_currency']) ? $_POST['mc_currency'] : '';
		$ipn_array_data['recurring'] = isset($_POST['recurring']) ? $_POST['recurring'] : '';
		$ipn_array_data['reattempt'] = isset($_POST['reattempt']) ? $_POST['reattempt'] : '';
		$ipn_array_data['retry_at'] = isset($_POST['retry_at']) ? $_POST['retry_at'] : '';
		$ipn_array_data['recur_times'] = isset($_POST['recur_times']) ? $_POST['recur_times'] : '';
		$ipn_array_data['username'] = isset($_POST['username']) ? $_POST['username'] : '';
		$ipn_array_data['password'] = isset($_POST['password']) ? $_POST['password'] : '';
		$ipn_array_data['subscr_id'] = isset($_POST['subscr_id']) ? $_POST['subscr_id'] : '';
		
		// Dispute Notification Variables
		$ipn_array_data['receipt_id'] = isset($_POST['receipt_id']) ? $_POST['receipt_id'] : '';
		$ipn_array_data['case_id'] = isset($_POST['case_id']) ? $_POST['case_id'] : '';
		$ipn_array_data['case_type'] = isset($_POST['case_type']) ? $_POST['case_type'] : '';
		$ipn_array_data['case_creation_date'] = isset($_POST['case_creation_date']) ? $_POST['case_creation_date'] : '';

		// Adaptive Payments Fields
		$ipn_array_data['transaction_type'] = isset($_POST['transaction_type']) ? $_POST['transaction_type'] : '';
		$ipn_array_data['status'] = isset($_POST['status']) ? $_POST['status'] : '';
		$ipn_array_data['sender_email'] = isset($_POST['senderEmail']) ? $_POST['senderEmail'] : '';
		$ipn_array_data['action_type'] = isset($_POST['actionType']) ? $_POST['actionType'] : '';
		$ipn_array_data['payment_request_date'] = isset($_POST['payment_request_date']) ? $_POST['payment_request_date'] : '';
		$ipn_array_data['reverse_all_parallel_payments_on_error'] = isset($_POST['reverse_all_parallel_payments_on_error']) ? $_POST['reverse_all_parallel_payments_on_error'] : '';

		$ipn_array_data['adaptive_payment_transactions'] = array();
		$i = 0;
		while(isset($_POST['transaction' . $i . '.id']))
		{
			$ipn_array_data['transaction_id'] = isset($_POST['transaction' . $i . '.id']) ? $_POST['transaction' . $i . '.id'] : '';
			$ipn_array_data['transaction_status'] = isset($_POST['transaction' . $i . '.status']) ? $_POST['transaction' . $i . '.status'] : '';
			$ipn_array_data['transaction_id_for_sender'] = isset($_POST['transaction' . $i . '.id_for_sender']) ? $_POST['transaction' . $i . '.id_for_sender'] : '';
			$ipn_array_data['tranasction_status_for_sender_txn'] = isset($_POST['transaction' . $i . '.status_for_sender_txn']) ? $_POST['transaction' . $i . '.status_for_sender_txn'] : '';
			$ipn_array_data['transaction_refund_id'] = isset($_POST['transaction' . $i . '.refund_id']) ? $_POST['transaction' . $i . '.refund_id'] : '';
			$ipn_array_data['transaction_refund_amount'] = isset($_POST['transaction' . $i . '.refund_amount']) ? $_POST['transaction' . $i . '.refund_amount'] : '';
			$ipn_array_data['transaction_refund_account_charged'] = isset($_POST['transaction' . $i . '.refund_account_charged']) ? $_POST['transaction' . $i . '.refund_account_charged'] : '';
			$ipn_array_data['transaction_receiver'] = isset($_POST['transaction' . $i . '.receiver']) ? $_POST['transaction' . $i . '.receiver'] : '';
			$ipn_array_data['transaction_invoice_id'] = isset($_POST['transaction' . $i . '.invoiceId']) ? $_POST['transaction' . $i . '.invoiceId'] : '';
			$ipn_array_data['transaction_amount'] = isset($_POST['transaction' . $i . '.amount']) ? $_POST['transaction' . $i . '.amount'] : '';
			$ipn_array_data['transaction_is_primary_receiver'] = isset($_POST['transaction' . $i . '.is_primary_receiver']) ? $_POST['transaction' . $i . '.is_primary_receiver'] : '';
			$ipn_array_data['current_transaction'] = array(
										 'transaction_id' => $ipn_array_data['transaction_id'], 
										 'transaction_status' => $ipn_array_data['transaction_status'], 
										 'transaction_id_for_sender' => $ipn_array_data['transaction_id_for_sender'], 
										 'transaction_status_for_sender_txn' => $ipn_array_data['transaction_status_for_sender_txn'], 
										 'transaction_refund_id' => $ipn_array_data['transaction_refund_id'], 
										 'transaction_refund_amount' => $ipn_array_data['transaction_refund_amount'], 
										 'transaction_refund_account_charged' => $ipn_array_data['transaction_refund_account_charged'], 
										 'transaction_receiver' => $ipn_array_data['transaction_receiver'], 
										 'transaction_invoice_id' => $ipn_array_data['transaction_invoice_id'], 
										 'transaction_amount' => $ipn_array_data['transaction_amount'], 
										 'transaction_is_primary_receiver' => $ipn_array_data['transaction_is_primary_receiver']
										 );
			array_push($ipn_array_data['adaptive_payment_transactions'], $ipn_array_data['current_transaction']);
			$i++;
		}

		$ipn_array_data['return_url'] = isset($_POST['returnUrl']) ? $_POST['returnUrl'] : '';
		$ipn_array_data['cancel_url'] = isset($_POST['cancelUrl']) ? $_POST['cancelUrl'] : '';
		$ipn_array_data['ipn_notification_url'] = isset($_POST['ipnNotificationUrl']) ? $_POST['ipnNotificationUrl'] : '';
		$ipn_array_data['pay_key'] = isset($_POST['payKey']) ? $_POST['payKey'] : '';
		$ipn_array_data['fees_payer'] = isset($_POST['feesPayer']) ? $_POST['feesPayer'] : '';
		$ipn_array_data['tracking_id'] = isset($_POST['trackingId']) ? $_POST['trackingId'] : '';
		$ipn_array_data['preapproval_key'] = isset($_POST['preapprovalKey']) ? $_POST['preapprovalKey'] : '';
		$ipn_array_data['approved'] = isset($_POST['approved']) ? $_POST['approved'] : '';
		$ipn_array_data['current_number_of_payments'] = isset($_POST['current_number_of_payments']) ? $_POST['current_number_of_payments'] : '';
		$ipn_array_data['current_total_amount_of_all_payments'] = isset($_POST['current_total_amount_of_all_payments']) ? $_POST['current_total_amount_of_all_payments'] : '';
		$ipn_array_data['current_period_attempts'] = isset($_POST['current_period_attempts']) ? $_POST['current_period_attempts'] : '';
		$ipn_array_data['currency_code'] = isset($_POST['currencyCode']) ? $_POST['currencyCode'] : '';
		$ipn_array_data['date_of_month'] = isset($_POST['date_of_month']) ? $_POST['date_of_month'] : '';
		$ipn_array_data['day_of_week'] = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : '';
		$ipn_array_data['starting_date'] = isset($_POST['starting_date']) ? $_POST['starting_date'] : '';
		$ipn_array_data['ending_date'] = isset($_POST['ending_date']) ? $_POST['ending_date'] : '';
		$ipn_array_data['max_total_amount_of_all_payments'] = isset($_POST['max_total_amount_of_all_payments']) ? $_POST['max_total_amount_of_all_payments'] : '';
		$ipn_array_data['max_amount_per_payment'] = isset($_POST['max_amount_per_payment']) ? $_POST['max_amount_per_payment'] : '';
		$ipn_array_data['max_number_of_payments'] = isset($_POST['max_number_of_payments']) ? $_POST['max_number_of_payments'] : '';
		$ipn_array_data['payment_period'] = isset($_POST['payment_period']) ? $_POST['payment_period'] : '';
		$ipn_array_data['pin_type'] = isset($_POST['pin_type']) ? $_POST['pin_type'] : '';

		// Adaptive Accounts
		$ipn_array_data['account_key'] = isset($_POST['account_key']) ? $_POST['account_key'] : '';
		$ipn_array_data['confirmation_code'] = isset($_POST['confirmation_code']) ? $_POST['confirmation_code'] : '';
		return $ipn_array_data;
	}
	
	#####################################
	#									#
	#			 IPN Settings			#
	#									#
	#####################################
	function ipn_settings()
	{
		global $db;
		$settings = array();
		$query = $db->simple_select('mysubs_settings');
		while($result = $db->fetch_array($query))
		{
			$settings[$result['name']] = $result['value'];
		}
		$settings['req'] = 'cmd=_notify-validate';
		$settings['test_ipn'] = isset($_POST['test_ipn']) ? true : false;
		$host_split = explode('.',$_SERVER['HTTP_HOST']);
		$settings['sandbox'] = $host_split[0] == 'sandbox' ? true : false;
		$settings['ppHost'] = 'www.paypal.com';
		$settings['ssl'] = $_SERVER['SERVER_PORT'] == '443' ? true : false;
		$php_version = explode('.',phpversion());
		$settings['php_version'] = $php_version[0];
		return $settings;
	}
	
	#####################################
	#									#
	#		IPN Validation			#
	#									#
	#####################################
	function ipn_validate($settings)
	{
		if(!$settings['curl_validation'])
		{
			// Validate with fsock
			if($settings['ssl'])
			{
				// Use SSL encryption
				$header = '';
				$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
				$header .= "Host: ".$settings['ppHost'].":443\r\n";
				$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$header .= "Content-Length: ".strlen($settings['req'])."\r\n\r\n";
				$fp = fsockopen('ssl://'.$settings['ppHost'], 443, $errno, $errstr, 30);
			}
			else
			{
				$header = '';
				$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
				$header .= "Host: ".$settings['ppHost'].":80\r\n";
				$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$header .= "Content-Length: ".strlen($settings['req'])."\r\n\r\n";
				$fp = fsockopen($settings['ppHost'], 80, $errno, $errstr, 30);
			}
			
			if (!$fp)
				return false;
			else
			{
				fputs($fp, $header.$settings['req']);
				while(!feof($fp))
				{
					$res = fgets($fp, 1024);
					if(strcmp($res, "VERIFIED") == 0)
						return true;
					else if(strcmp($res, "INVALID") == 0)
						return false;
				}
				fclose($fp);
			}
		}
		else
		{
			// Validate with curl
			$curl_result = $curl_err = '';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $settings['ppHost'].'/cgi-bin/webscr');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $settings['req']);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "Content-Length: ".strlen($req)));
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $settings['ssl']);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);

			$curl_result = curl_exec($ch);
			$curl_err = curl_error($ch);
			curl_close($ch);
			
			if (strpos($curl_result, "VERIFIED") !== false) 
				return true;
			else
				return false;
		}
	}
	
	function mysubs_usercp_menu()
	{
		global $db, $mybb, $usercpmenu;

		$mysubs = '<tr><td class="trow1 smalltext"><a href="'.$mybb->settings['bburl'].'/misc.php?action=payments" class="usercp_nav_item" style="background: url('.$mybb->settings['bburl'].'/images/usercp/dollar.gif) no-repeat scroll left center transparent;">MySubscriptions</a></td></tr>';
		$usercpmenu .= $mysubs;
	}

?>