<?php

class Controllers_timesheet extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'timesheet' => 'GetTimesheet'
            ),
            'put' => array(
                'timesheet' => 'EditTimesheet'
            )
        );
    }

    public function GetTimesheet()
    {
        $userid = intval($this->getRequestParamValue('userid', false));
        $taskid = intval($this->getRequestParamValue('taskid', false));
        $from   = intval($this->getRequestParamValue('from', false));
        $count  = intval($this->getRequestParamValue('count', false));

        $this->response = array(
            "status" => 0
        );

        /* search condition */
        $where = array();

        if ($userid) {
            $this->response['userid'] = $userid;

            $where[] = "`userid`={$userid}";
        }

        if ($taskid) {
            $this->response['taskid'] = $taskid;

            $where[] = "`taskid`={$taskid}";
        }

        if ($from && $count) {
            $this->response['from'] = $from;
            $this->response['count'] = $count;

            $to = $from + $count;
            $where[] = "`day` >= {$from} AND `day` < {$to}";
        }

        if (empty($where))
            throw new Exception('Search params are empty', 400);

        $where = implode(" AND ", $where);
        /* end of search condition*/

        $items = array();
        $query = mysql_query("SELECT * FROM `todo_timesheet` WHERE {$where}") or $this->throwMySQLError();

        while( $obj = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $obj );
        }

        $this->response["items"] = $items;
        $this->responseStatus = 200;
    }

    public function EditTimesheet()
    {
        $userid  = intval($this->getRequestBodyValue('userid', false));
        $day = intval($this->getRequestBodyValue('day', true));
        $taskid = intval($this->getRequestBodyValue('taskid', true));
        $worktimeSeconds = intval($this->getRequestBodyValue('worktimeSeconds', true));

        $controllers_task = new Controllers_task($this->request);
        $sheets = $controllers_task->checkTaskExists($taskid);

        // TODO: если не задан юзерайди, то приравнять его к текущему юзеру

        if ($worktimeSeconds>0)
            mysql_query("INSERT INTO `todo_timesheet` (day, userid, taskid, worktimeSeconds) VALUES ({$day}, {$userid}, {$taskid}, {$worktimeSeconds}) ON DUPLICATE KEY UPDATE worktimeSeconds={$worktimeSeconds};") or $this->throwMySQLError();
        else
            mysql_query("DELETE FROM `todo_timesheet` WHERE day='{$day}' AND userid='{$userid}' AND taskid='{$taskid}'") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );

        $this->responseStatus = 202;
    }
}

?>
