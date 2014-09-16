<?php

class Controllers_timesheet extends RestController 
{
	function _getTaskTimesheets($task_id)
	{
		$items = array();
		
		$query = mysql_query( "SELECT * FROM `todo_timesheet` WHERE task='{$task_id}'" ) or $this->throwMySQLError();
		while( $dbtask = mysql_fetch_array( $query, MYSQL_ASSOC ) )
		{
			$items[] = $dbtask;
		}
		
		return $items;
	}
	
	function _getUserTimesheet($worker = 0, $day = 0)
	{
		if (!$day || !$worker)
			return null;
			
		$response = array();
		
		$response['totalWorktimeSeconds'] = 0;
		$response['items'] = array();
		
		$query = mysql_query("SELECT task, SUM(worktimeSeconds) AS worktimeSeconds FROM `todo_timesheet` GROUP BY worker, day HAVING worker='{$worker}' AND day='{$day}'");
		while( $dbtask = mysql_fetch_array( $query, MYSQL_ASSOC ) )
		{
			$response['items'][] = $dbtask;
			
			$response['totalWorktimeSeconds'] += $dbtask['worktimeSeconds'];
		}
		
		return $response;
	}
	
	function saveTimesheetRow($task, $worker)
	{
		$day = $this->getRequestBodyValue('day');		
		$worktimeSeconds = $this->getRequestBodyValue('worktimeSeconds');

		if ($worktimeSeconds>0)
			mysql_query("INSERT INTO `todo_timesheet` (day, worker, task, worktimeSeconds) VALUES ({$day}, {$worker}, {$task}, {$worktimeSeconds}) ON DUPLICATE KEY UPDATE worktimeSeconds={$worktimeSeconds};") or $this->throwMySQLError();
		else
			mysql_query("DELETE FROM `todo_timesheet` WHERE day='{$day}' AND worker='{$worker}' AND task='{$task}'") or $this->throwMySQLError();
	}
	
	
	public function get() {	
		return null;
	}
	
	public function put() {
		return null;
	}
	
	public function post() {
        return null;
	}
     
	public function delete() {		
		return null;
	}
}

?>
