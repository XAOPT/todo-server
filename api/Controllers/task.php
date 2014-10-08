<?php

class Controllers_task extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'task' => 'GetTasks'
            ),
            'post' => array(
                'task' => 'CreateTask',
                'task/\d+/attachment' => 'AttachFile'
            ),
            'put' => array(
                'task/\d+' => 'EditTask'
            ),
            'delete' => array(
                'task/\d+' => 'DeleteTask'
            )
        );
    }

    public function checkTaskExists($task_id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_task` WHERE id='{$task_id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    ### возвращает инфо о задаче
    public static function createTaskFromDatabaseObject( $dbobj )
    {
        if ( !isset( $dbobj ) )
            return null;

        $taskid = (int)$dbobj['id'];

        $task = array(
            'id'          => $taskid,
            'project'     => (int)$dbobj['project'],
            'type'        => (string)$dbobj['type'],
            'title'       => (string)$dbobj['title'],
            'priority'    => (int)$dbobj['priority'],
            'status'      => (string)$dbobj['status'],
            'assignee'    => (int)$dbobj['assignee'],
            'parentTask'  => (int)$dbobj['parentTask'],

            'estimatedEffortSeconds' => intval($dbobj['estimateSeconds']),
            'startDate'   => strtotime($dbobj['startDate']),
            'duration'    => intval($dbobj['duration']),
            'deadline'    => strtotime($dbobj['deadline']),

            'created'     => strtotime($dbobj['created']),
            'createdby'   => $dbobj['createdby'],
            'modified'    => strtotime($dbobj['modified']),
            'modifiedby'  => $dbobj['modifiedby'],
        );

        return $task;
    }

    public function GetTasks()
    {
        $id        = intval($this->getRequestParamValue('id', false));
        $project   = intval($this->getRequestParamValue('project', false));
        $title     = $this->getRequestParamValue('title', false);
        $text      = $this->getRequestParamValue('text', false);
        $assignee  = intval($this->getRequestParamValue('assignee', false));
        $status    = $this->getRequestParamValue('status', false);
        $priority  = intval($this->getRequestParamValue('priority', false));
        $timesheet_from = intval($this->getRequestParamValue('timesheet_from', false));
        $timesheet_to   = intval($this->getRequestParamValue('timesheet_to', false));
        $parent    = intval($this->getRequestParamValue('parent', false));

        $from      = intval($this->getRequestParamValue('from', false));
        $count     = intval($this->getRequestParamValue('count', false));

        if (!$from) $from = 0;
        if (!$count) $count = 100;

        $where = array();

        if ($id) {
            $where[] = "`id`={$id}";
        }
        if ($project) {
            $where[] = "`project`={$project}";
        }
        if ($title) {
            $where[] = "`title` LIKE '%{$title}%'";
        }
        if ($text) {
            $where[] = "`text` LIKE '%{$text}%'";
        }
        if ($assignee) {
            $where[] = "`assignee`={$assignee}";
        }
        if ($status) {
            $where[] = "`status`={$status}";
        }
        if ($priority) {
            $where[] = "`priority`={$priority}";
        }
        if ($parent) {
            $where[] = "`parentTask`={$parent}";
        }

        if (empty($where))
            throw new Exception('Search params are empty', 400);

        $where = implode(" AND ", $where);

        $items = array();
        $query = mysql_query("SELECT * FROM `todo_task` WHERE {$where} LIMIT {$from}, {$count}") or $this->throwMySQLError();

        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $dbtask );
        }

        $this->response = array(
            "status" => 0,
            "from" => $from,
            "count" => $count,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function CreateTask()
    {
        /*if ( !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $project_id ) )
            $this->throwForbidden();*/

        $data = $this->GetParamsFromRequestBody('create');

        $this->insertArrayIntoDatabase('todo_task', $data);
        $id = mysql_insert_id();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
        $this->responseStatus = 201;
    }

    public function EditTask()
    {
        $task_id = intval($this->getResourceNamePart( 1 ));

        $this->checkTaskExists($task_id);

        $data = $this->GetParamsFromRequestBody('edit');

        $this->UpdateDatabaseFromArray('todo_task', $data, "id='{$task_id}'");

        $this->response = array(
            "status" => 0,
            "id" => $task_id
        );

        $this->responseStatus = 202; // accepted

        /*if (
        !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] )
        && !$this->loggedUser->hasProjectPermission( User::PERMISSION_TESTER, $task['project'] )
        && $this->loggedUser->getId() != $task['assignee']
        )
            $this->throwForbidden();*/
    }

    public function DeleteTask()
    {
        $task_id = intval($this->getResourceNamePart( 1 ));

        $this->checkTaskExists($task_id);

       /* if ( !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] ) )
            $this->throwForbidden();*/

        $query  = mysql_query("SELECT * FROM `todo_task` WHERE parentTask='{$task_id}' LIMIT 0,1");

        if ( mysql_num_rows( $query ) > 0 )
        {
            $this->response = array(
                "status" => 101,
                "message" => "Task has childes"
            );
            $this->responseStatus = 200;
        }

        mysql_query("DELETE FROM `todo_task` WHERE id='{$task_id}'");

        ##!!!!!!!!!!!!!!!!!! TODO: timesheet clean
        ##!!!!!!!!!!!!!!!!!! TODO: comment clean

        $this->response = array("status" => 0);
        $this->responseStatus = 200;

        return;
    }

    function AttachFile()
    {
        $task_id = $this->getResourceNamePart( 1 );

        $filesize = intval($_GET['size']);

        $file_name = mysql_real_escape_string($_GET['filename']);
        /*$file_name = preg_replace("/[^a-zA-Z0-9\.]/", "", $file_name);

        $allowed_extensions = array("doc", "txt", "rtf", "docx", "csv", "xml", "xss", "gif", "jpg", "png", "jpe", "jpeg");

        $file_name_arr = explode(".", $file_name);
        $file_type     = end($file_name_arr);

        if (!in_array(strtolower($file_type), $allowed_extensions))
        {
            $this->response = 'file type is not allowed';
            $this->responseStatus = 406;
            break;
        }

        if (file_exists(APPLICATION_PATH.'/attachments/'.$file_name))
        {
            $this->response = 'file with that name already exists';
            $this->responseStatus = 406;
            break;
        }   */

        //file_put_contents(APPLICATION_PATH.'/attachments/'.$file_name, $this->request['body']);

        mysql_query("INSERT INTO `todo_attachment` (task, user_id, filename, size) VALUES ('{$task_id}', '{$worker}', '{$file_name}', '{$filesize}')") or $this->throwMySQLError();
        $attach_id = mysql_insert_id();

        file_put_contents(APPLICATION_PATH.'/attachments/'.$attach_id, $this->request['body']);

        $this->response = array('id', $attach_id);
        $this->responseStatus = 402;
    }




/*    private function _getTaskFromDatabase( $task_id )
    {
        $query = mysql_query( "SELECT * FROM `todo_task` WHERE `id`='{$task_id}'" ) or $this->throwMySQLError();

        if ( $task = mysql_fetch_array( $query ) )
            return $task;

        return null;
    }*/

   /* public function get()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
                ## task/<id>/oldnote [GET]: temporary function
                if ( $this->getResourceNamePart( 2 ) == 'oldnote' )
                {
                    $task_id = $this->getResourceNamePart( 1 );

                    $query = mysql_query( "SELECT notes FROM `task` WHERE `id`='{$task_id}'" ) or $this->throwMySQLError();
                    $task = mysql_fetch_array( $query );

                    echo $task['notes']; exit;
                    $this->responseStatus = 200;
                }
                ## task/<id>/oldcalendar [GET]: temporary function
                if ( $this->getResourceNamePart( 2 ) == 'oldcalendar' )
                {
                    $task_id = $this->getResourceNamePart( 1 );

                    $query = mysql_query( "SELECT calendar FROM `task` WHERE `id`='{$task_id}'" ) or $this->throwMySQLError();
                    $task = mysql_fetch_array( $query );

                    echo $task['calendar']; exit;
                    $this->responseStatus = 200;
                }
        }

        return null;
    }*/
}

?>
