<?php

class Controllers_timesheet extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'timesheet' => 'GetTimesheet',
                'timesheet/\d+/summary' => 'GetSummaryTimesheet'
            ),
            'put' => array(
                'timesheet' => 'EditTimesheet'
            )
        );
    }

    public function GetTimesheet()
    {
        $userid = intval($this->getRequestParamValue('userid', false));
        $taskid = $this->getRequestParamValue('taskid', false);
        $projid = $this->getRequestParamValue('projid', false);
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

            if (is_array($taskid)) {
                foreach ($taskid as &$t) {
                    $t = intval($t);
                }
                $taskid = implode(',', $taskid);
            }
            else
                $taskid = intval($taskid);

            $where[] = "`taskid` IN ({$taskid})";
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

        if (!$projid) {
            $query = mysql_query("SELECT * FROM `todo_timesheet` WHERE {$where}") or $this->throwMySQLError();
        }
        else {
            if (is_array($projid)) {
                foreach ($projid as &$p) {
                    $p = intval($p);
                }
                $projid = implode(',', $projid);
            }
            else {
                $projid = intval($projid);
            }

            $query = mysql_query("
                SELECT tsh.*, t.project
                FROM `todo_timesheet` AS tsh
                LEFT JOIN `todo_task` AS t ON tsh.taskid=t.id
                WHERE {$where} AND t.project IN ({$projid})
            ") or $this->throwMySQLError();
        }

        while( $obj = mysql_fetch_array( $query ) )
        {
            $temp = $this->normalizeObject( $obj );

            if (isset($obj['project']))
                $temp['project'] = $obj['project'];

            $items[] = $temp;
        }

        $this->response["items"] = $items;
        $this->responseStatus = 200;
    }

    public function GetSummaryTimesheet()
    {
        $userid = intval($this->getResourceNamePart( 1 ));

        $from   = intval($this->getRequestParamValue('from', true));
        $count  = intval($this->getRequestParamValue('count', true));

        $to = $from + $count;

        $items = array();
        $query = mysql_query("SELECT SUM(worktimeSeconds) AS worktimeSeconds, day FROM `todo_timesheet` WHERE `userid`={$userid} AND `day`>={$from} AND `day`<{$to} GROUP BY day") or $this->throwMySQLError();

        while( $row = mysql_fetch_assoc( $query ) )
        {
            $items[] = $row;
        }

        $this->response = array(
            "status" => 0,
            "from"  => $from,
            "count" => $count,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function EditTimesheet()
    {
        $data = $this->GetParamsFromRequestBody('edit');

        $controllers_task = new Controllers_task($this->request);
        $sheets = $controllers_task->checkTaskExists($data['taskid']);

        // TODO: если не задан юзерайди, то приравнять его к текущему юзеру
        if ($data['worktimeSeconds'] > 0)
        {
            if (!isset($data['comment']))
                $data['comment'] = '';

            mysql_query("
                INSERT INTO `todo_timesheet` (day, userid, taskid, worktimeSeconds, comment)
                VALUES ({$data['day']}, {$data['userid']}, {$data['taskid']}, {$data['worktimeSeconds']}, '{$data['comment']}')
                ON DUPLICATE KEY UPDATE worktimeSeconds={$data['worktimeSeconds']}, comment='{$data['comment']}'"
            ) or $this->throwMySQLError();
        }
        else
            mysql_query("DELETE FROM `todo_timesheet` WHERE day={$data['day']} AND userid={$data['userid']} AND taskid={$data['taskid']}") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );

        $this->responseStatus = 202;
    }
}

?>
