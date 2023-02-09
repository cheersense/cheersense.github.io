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
	 *	English language pack.
	 *
	 */
	
	$l['name'] = 'Name';
	$l['name_desc'] = 'The name of the subscription.';
	$l['description'] = 'Description';
	$l['description_desc'] = 'This is shown beneath the subscription option when your members go to purchase.';
	$l['admin_desc'] = 'Admin Description';
	$l['admin_desc_desc'] = 'A brief explanation of the subscription. Only visible from the admin cp.';
	$l['new_group'] = 'New Group';
	$l['new_group_desc'] = 'The group you would like to place the member after purchasing this subscription.';
	$l['accepted_groups'] = 'Accepted Groups';
	$l['accepted_groups_desc'] = 'These are the groups that are allowed to purchase this subscription. Use CTRL to select multiple groups. Leave blank to allow all.';
	$l['item_name'] = 'Item Name';
	$l['item_name_desc'] = 'This is shown when the user is paying for your subscription. This must be alpha-numeric and no more than 127 characters long.';
	$l['active'] = 'Active';
	$l['active_desc'] = 'Is this an active subscription? Select yes if you would like your members to be able to purchase this subscription.';
	
	$l['currency'] = 'Currency';
	$l['currency_desc'] = 'The currency you want to accept payments in. <strong>Note:</strong> Paypal might charge extra for conversions.';
	$l['price_length'] = 'Price / Length';
	$l['price_desc'] = 'Here you can set the pricing options for the subscription. You can set as many as you want. For a permanent subscription, keep the length at 0.';
	$l['recurring'] = 'Recurring';
	$l['recurring_desc'] = 'Should this be a recurring subscription? <strong>Note:</strong> Some users are unable to purchase items via subscriptions.';
	
	$l['error_duration_too_long'] = 'The duration you entered is too long to be stored. Consider allowing permanent subscriptions.';
	$l['error_empty_name'] = 'You have to enter a name for the subscription.';
	$l['error_fail_delete'] = 'The subscription failed to delete.';
	$l['error_fail_save'] = 'The subscription failed to save correctly.';
	$l['error_invalid_currency'] = 'You selected an invalid currency.';
	$l['error_invalid_group'] = 'You selected an invalid user group.';
	$l['error_invalid_item_name'] = 'You entered an invalid item name.';
	$l['error_invalid_notif'] = "That notification doesn't exist.";
	$l['error_invalid_price'] = 'You entered an invalid price.';
	$l['error_invalid_subscription'] = 'You have selected an invalid subscription.';
	
	$l['success_sub_add'] = 'You have successfully created a new subscription option.';
	$l['success_sub_delete'] = 'The subscription has been deleted.';
	$l['success_save'] = 'Your changes have been saved.';
	
	$l['setting_enabled'] = 'Enabled';
	$l['setting_enabled_desc'] = 'Would you like to enable MySubscriptions?';
	$l['setting_pp_email'] = 'Paypal Email';
	$l['setting_pp_email_desc'] = 'Enter the primary email address associated with your paypal account.';
	$l['setting_updates'] = 'Updates';
	$l['setting_updates_desc'] = 'What would you like to receive when a payment is processed?'; 
	
	$l['setting_use_fsock'] = 'Fsock';
	$l['setting_use_fsock_desc'] = 'If selected, fsock will be used to validate payments, otherwise curl will be used.';
	$l['setting_use_ssl'] = 'SSL';
	$l['setting_use_ssl_desc'] = 'If selected, MySubscriptions will use SSL whenever possible. Select this if you have an ssl server.';
	
	$l['add'] = 'Add';
	$l['add_sub'] = 'Add Subscription';
	$l['add_sub_desc'] = 'Here you can add a new subscription option for your forum.';
	$l['advanced_settings'] = 'Advanced Settings';
	$l['alt_unactive'] = 'Deactived';
	$l['basic_settings'] = 'Basic Settings';
	$l['confirm_sub_deletion'] = 'Are you sure you want to delete this subscription option?';
	$l['cost'] = 'Cost';
	$l['delete_sub'] = 'Delete Subscription';
	$l['edit_sub'] = 'Edit Subscription';
	$l['expiration'] = 'Expiration';
	$l['forever'] = 'Forever';
	$l['item'] = 'Item';
	$l['length'] = 'Length';
	$l['manage_subs'] = 'Manage Subscriptions';
	$l['manage_subs_desc'] = 'Here you can manage paid subscriptions on your board.';
	$l['mysubs'] = 'MySubscriptions';
	$l['notif'] = 'Notifications';
	$l['notif_desc'] = 'Here you can manage all payment notifications sent.';
	$l['no_notifs'] = 'There are no notifications yet.';
	$l['no_settings'] = "There are no settings. (This shouldn't happen, contact the author)";
	$l['no_subs'] = 'You do not currently have any subscriptions set.';
	$l['price'] = 'Price';
	$l['price_settings'] = 'Price Settings';
	$l['save_settings'] = 'Save Settings';
	$l['save_sub'] = 'Save Subscription';
	$l['settngs'] = 'Settings';
	$l['settngs_desc'] = 'Here you can configure the settings for MySubscriptions.';
	$l['subscription'] = 'Subscription';
	$l['success'] = 'Success';
	$l['time'] = 'Time';
	$l['type'] = 'Type';
	$l['user'] = 'User';
	$l['view_details'] = 'View Details';
	$l['view_subs'] = 'View Subscribers';
	
	$l['task_mysubs_ran'] = 'MySubscriptions ran successfully.';

?>