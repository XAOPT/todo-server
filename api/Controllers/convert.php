<?php

class Controllers_convert extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'convert/users' => 'ConvertUsers',
                'convert/projects' => 'ConvertProjects',
                'convert/tasks' => 'ConvertTasks'
            )
        );
    }

    public function ConvertUsers()
    {
        $query = mysql_query( "SELECT * FROM `user`" ) or $this->throwMySQLError();

        mysql_query("TRUNCATE TABLE `todo_user`");
        mysql_query("TRUNCATE TABLE `todo_timesheet`");

        while( $d = mysql_fetch_array( $query ) )
        {
            $vars = array();

            if ($d['email']) $vars['email'] = $d['email'];
            if ($d['birthday']) $vars['birthday'] = $d['birthday'];

            $data = array(
                'id'         => $d['id'],
                'email'      => $d['login'],
                'role'       => $d['role'],
                'group'      => $d['group'],
                'firstname'  => $d['firstName'],
                'lastname'   => $d['lastName'],
                'vars'       => json_encode($vars),
                'clientSettings' => '',
                'created'    => $d['created'],
                'createdby'  => 0,
                'deleted'    => $d['deleted'],
            );

            $this->insertArrayIntoDatabase('todo_user', $data);
        }

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }

    public function ConvertProjects()
    {
        $query = mysql_query( "SELECT * FROM `project`" ) or $this->throwMySQLError();

        mysql_query("TRUNCATE TABLE `todo_project`");
        while( $d = mysql_fetch_array( $query ) )
        {
            $data = array(
                'id'         => $d['id'],
                'title'      => mysql_real_escape_string($d['title']),
                'shorttitle' => $d['abc'],
                'tagcolor'   => dechex(abs($d['color'])),
                'created'    => $d['created']
            );

            $this->insertArrayIntoDatabase('todo_project', $data);
        }

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }

    public function ConvertTasks()
    {
        $query = mysql_query( "SELECT *, HEX(calendar) AS calendar FROM `task` WHERE calendarVersion IS NOT NULL" ) or $this->throwMySQLError();
        mysql_query("TRUNCATE TABLE `todo_task`");
        mysql_query("TRUNCATE TABLE `todo_timesheet`");

        $status_map = array('open', 'inprogress', 'finished', 'closed', 'canceled', 'reopened');


        while( $d = mysql_fetch_array( $query ) )
        {
            $data = array(
                'id'         => $d['id'],
                'project'    => $d['projid'],
                'type'       => 'task',
                'title'      => $d['title'],
                'priority'   => $d['priority'],
                'status'     => $status_map[$d['state']],
                'assignee'   => $d['uid'],
                'parentTask' => $d['parent'],
                'estimateSeconds' => $d['effort']*3600,
                'grade'     => $d['grade'],
                'deadline'  => $d['deadline'],
                'created'   => $d['created'],
                'createdby' => 0,
                'modified'   => $d['modified'],
                'modifiedby' => 0
            );

            $this->insertArrayIntoDatabase('todo_task', $data);

            if ($d['calendar']) {
                $this->fillTimesheet($d['uid'], $d['id'], $d['calendar']);
            }
        }

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }

    private function fillTimesheet($userid, $taskid, $binary)
    {
        $parts = hexdec(substr($binary, 0, 2));

        for ($i = 0; $i < $parts; $i++) {
            $start = 8 + $i*18;
            $date  = substr($binary, $start, 8);
            $hours = substr($binary, $start+8, 8);

            $date = str_split($date, 2);
            $date = hexdec($date[3].$date[2])."-".sprintf("%02d", hexdec($date[1]))."-".sprintf("%02d", hexdec($date[0]));
            if (strcmp($hours, "0000000")) {
                $hours = round($this->hexTo32Float("0x".substr($hours, 6, 2).substr($hours, 4, 2).strrev(substr($hours, 0, 4))), 1);

                $data = array(
                    "day" => intval(strtotime($date)/86400)+1,
                    "userid" => $userid,
                    "taskid" => $taskid,
                    "worktimeSeconds" => $hours*3600
                );

                $this->insertArrayIntoDatabase('todo_timesheet', $data);
            }
        }
    }

    private function hexTo32Float($strHex) {
        $v = hexdec($strHex);
        $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
        $exp = ($v >> 23 & 0xFF) - 127;
        return $x * pow(2, $exp - 23);
    }
}

?>
