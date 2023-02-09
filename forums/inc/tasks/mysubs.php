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
	
	function task_mysubs($task)
	{
		global $db, $lang;
		$lang->load('user_mysubs');
		
		/**
		 * TODO:
		 *
		 * - Check for expired subscriptions.
		 * - Email subscription updates (optional).
		 *
		 */
		$result = $db->simple_select('mysubs_notifs', '*', "`active` = 1 AND `expiration` > 0 AND `expiration` < ".TIME_NOW);
		if($result && $db->num_rows($result) > 0)
		{
			// A subscription expired.
			while($sub = $db->fetch_array($result))
			{
				$db->update_query('users', array('usergroup' => $sub['old_gid']), "`uid` = {$sub['uid']}");
				$db->update_query('mysubs_notifs', array('active' => 0));
			}
		}
		
		add_task_log($task, $lang->task_mysubs_ran);
	}

?>