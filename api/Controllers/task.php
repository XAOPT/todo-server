<?php

class Controllers_task extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'task/search'          => 'SearchTask',
                'task/(\d+)'           => 'GetTaskById',
                'task/(\d+)/tasks'     => 'GetTaskChildes',
                'task/(\d+)/timesheet' => 'GetTaskTimesheet'
            ),
            'put' => array(
                'task/(\d+)'           => 'EditTask',
                'task/(\d+)/timesheet' => 'EditTaskTimeshit',
            ),
            'delete' => array(
                'task/(\d+)'           => 'DeleteTask'
            )
        );
    }

    private function _getTaskFromDatabase( $task_id )
    {
        $query = mysql_query( "SELECT * FROM `todo_task` WHERE `id`='{$task_id}'" ) or $this->throwMySQLError();

        if ( $task = mysql_fetch_array( $query ) )
            return $task;

        return null;
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

        // Collect subtask IDs.
        $subtasks = array();
        $query = mysql_query( "SELECT id FROM `todo_task` WHERE `parentTask`='{$taskid}'" );
        while( $subtask = mysql_fetch_array( $query ) )
        {
             $subtasks[] = array( 'id' => (int)$subtask['id'] );
        }

        if ( count($subtasks) > 0 )
            $task['sub-tasks'] = $subtasks;

        # rootComment - Вынести в контроллер комментов
        $query = mysql_query( "SELECT id FROM `todo_comment` WHERE `parentTask`='{$taskid}' AND `parentComment`='0'" );
        $comment = mysql_fetch_array( $query, MYSQL_NUM );
        if ( !empty($comment) )
            $task['rootComment'] = $comment[0];


        $attachments = array();
        $query = mysql_query( "SELECT * FROM `todo_attachment` WHERE `task`='{$taskid}'" ) or $this->throwMySQLError();
        while($dbtask = mysql_fetch_array( $query, MYSQL_ASSOC ))
        {
            $attachments[] = array(
                'url'      => APPLICATION_URL.'attachments/'.$dbtask['id'],
                'filename' => $dbtask['filename'],
                'size'     => $dbtask['size'],
            );
        }
        $task['attachments'] = $attachments;

        return $task;
    }

    /*------------------------------------------ ROUTES -------------------------------------------------------------*/

    public function GetTaskById()
    {
        $task_id = $this->getResourceNamePart( 1 );

        $db_task = $this->_getTaskFromDatabase( $task_id );

        if ( !isset( $db_task ) )
            throw new Exception( 'Not Found', 404 );

        $this->response = $this->createTaskFromDatabaseObject($db_task);
        $this->responseStatus = 200;

        return;
    }

    public function SearchTask()
    {
        $title     = getRequestParamValue('title');
        $assignee  = getRequestParamValue('assignee');
        $timesheet = getRequestParamValue('timesheet');
        $from      = intval(getRequestParamValue('from'));
        $count     = intval(getRequestParamValue('count'));

        if (!$from) $from = 0;
        if (!$count) $count = 100;


        if (!$title && !$assignee && !$timesheet && !$from)
        {
            $this->response = array();
            $this->responseStatus = 200;

            return;
        }

        $this->response = $this->GetTasksByTitle($text);
        $this->response = $this->GetOpenTasksByAssignee($assignee);
        $this->response = $this->GetTasksByAssigneesTimesheet($assignee, $dayfrom, $dayto);
        $this->response = $this->GetRootOpenTasks($from, $count);

        $this->responseStatus = 200;
        break;
    }

    public function DeleteTask()
    {
        $task_id = intval($this->getResourceNamePart( 1 ));

        $query = mysql_query( "SELECT project FROM `todo_task` WHERE id='{$task_id}'" ) or $this->throwMySQLError();
        $task  = mysql_fetch_array($query);

        if (empty($task))
            throw new Exception( 'Not Found', 404 );

       /* if ( !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] ) )
            $this->throwForbidden();*/

        $query  = mysql_query("SELECT * FROM `todo_task` WHERE parentTask='{$task_id}' LIMIT 0,1");

        if ( mysql_num_rows( $query ) > 0 )
        {
            $this->response = array('status' => 101, 'message' => 'Task has childes');
            $this->responseStatus = 200;
        }

        mysql_query("DELETE FROM `todo_task` WHERE id='{$task_id}'");

        ##!!!!!!!!!!!!!!!!!! TODO: timesheet clean
        ##!!!!!!!!!!!!!!!!!! TODO: comment clean

        $this->response = array('status' => 0);
        $this->responseStatus = 200;

        return;
    }

    public function EditTask()
    {
        $task_id = intval($this->getResourceNamePart( 1 ));

        $query = mysql_query( "SELECT project, assignee FROM `todo_task` WHERE id='{$task_id}'" ) or $this->throwMySQLError();
        $task  = mysql_fetch_array($query);

        if (empty($task))
            throw new Exception( 'Not Found', 404 );

        /*if (
        !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] )
        && !$this->loggedUser->hasProjectPermission( User::PERMISSION_TESTER, $task['project'] )
        && $this->loggedUser->getId() != $task['assignee']
        )
            $this->throwForbidden();*/

        /*if ($this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] ) )
            $editable_columns = array(
                'project'    => 'project',
                'startDate'  => 'startDate',
                'duration'   => 'duration',
                'estimatedEffortSeconds' => 'estimateSeconds',
                'type'       => 'type',
                'title'      => 'title',
                'priority'   => 'priority',
                'status'     => 'status',
                'assignee'   => 'assignee',
                'parentTask' => 'parentTask',
                'deadline'   => 'deadline'
            );
        else
            $editable_columns = array(
                'status'     => 'status'
            );*/

        /*$sets = $this->GetEditablesFromBody($editable_columns);
        $sets[] = 'modified=NOW()';
        $sets[] = "modifiedby='".$this->loggedUser->getId()."'";
        $sets = implode(',', $sets);*/

        /**/mysql_query( "UPDATE `todo_task` SET {$sets} WHERE id='{$task_id}'" ) or $this->throwMySQLError();

        $this->response = array("status" => 0);
        $this->responseStatus = 202; // accepted
        break;
    }

    public function GetTaskTimesheet()
    {
        $task_id = intval($this->getResourceNamePart( 1 ));

        $controllers_timesheet = new Controllers_timesheet($this->request);
        $sheets = $controllers_timesheet->_getTaskTimesheets($task_id, 0);

        $this->response = $sheets;
        $this->responseStatus = 200;
    }

    public function EditTaskTimeshit()
    {
        ## task/<id>/timesheet?day=15275
        $task_id = intval($this->getResourceNamePart( 1 ));

        $query = mysql_query( "SELECT project, assignee FROM `todo_task` WHERE id='{$task_id}'" ) or $this->throwMySQLError();
        $task  = mysql_fetch_array($query, MYSQL_ASSOC);

        if (empty($task))
            throw new Exception( 'Not Found', 404 );

        /* Залогиненый юзер должен быть либо assignee задачи, либо менеджером с пермишном task.management
        Если у юзера нет прав task.management, то worker не должен указываться, т.к. автоматически подразумевается assignee задачи. */
        /*if (
        !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] )
        && $this->loggedUser->getId() != $task['assignee']
        )
            $this->throwForbidden();*/

        /*$worker = 0;
        if ( $this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $task['project'] ) )
            $worker  = $this->getRequestBodyValue('worker', false);*/

        if (!$worker)
            $worker = $this->loggedUser->getId();

        $controllers_timesheet = new Controllers_timesheet($this->request);
        $sheets = $controllers_timesheet->saveTimesheetRow($task_id, $worker);

        $this->response = array("status" => 0);
        $this->responseStatus = 202; // accepted
    }

    /*-------------------------------------------------------------------------------------------------------*/

    ### Возвращает все корневые задачи для проекта
    public function GetProjectTasks($project_id)
    {
        $items = array();
        $query = mysql_query( "SELECT * FROM `todo_task` WHERE `project`={$project_id} AND `parentTask`=0" ) or $this->throwMySQLError();
        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->createTaskFromDatabaseObject( $dbtask );
        }

        return $items;
    }

    ### Возвращает все корневые открытые задачи
    private function GetRootOpenTasks($from, $count)
    {
        $items = array();
        $query = mysql_query( "SELECT * FROM `todo_task` WHERE `parentTask`=0 LIMIT {$from}, {$count}" ) or $this->throwMySQLError();
        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->createTaskFromDatabaseObject( $dbtask );
        }

        return $items;
    }

    private function GetOpenTasksByAssignee($assignee)
    {
        $items = array();
        $query = mysql_query( "SELECT * FROM `todo_task` WHERE `parentTask`=0 AND status <> 'close' AND assignee='{$assigne}'" ) or $this->throwMySQLError();
        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->createTaskFromDatabaseObject( $dbtask );
        }

        return $items;
    }

    private function GetTasksByAssigneesTimesheet($assignee, $dayfrom, $dayto)
    {
        $items = array();
        $query = "
            SELECT DISTINCT ts.task, t.*
            FROM `todo_timesheet` AS ts
            LEFT JOIN `todo_task` AS t ON (t.id=ts.task)
            WHERE worker='{$assignee}' AND day BETWEEN {$dayfrom} AND {$dayto}
        ";

        $query = mysql_query( $query ) or $this->throwMySQLError();
        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->createTaskFromDatabaseObject( $dbtask );
        }

        return $items;
    }

   public  function GetTasksByTitle($text)
    {
        $text = str_ireplace(" ","%",$text);

        $query = "
            SELECT *
            FROM `todo_task`
            WHERE title LIKE '%{$text}%'
        ";

        $query = mysql_query( $query ) or $this->throwMySQLError();
        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->createTaskFromDatabaseObject( $dbtask );
        }

        if (empty($items))
            throw new Exception( 'Not Found', 404 );

        return $items;
    }

     ## создаёт таск для проекта
    public function createTaskInDatabase($data)
    {
        $this->insertArrayIntoDatabase('todo_task', $data);
        $id = mysql_insert_id();
        return $id;
    }

    public function checkTaskExists($task_id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_task` WHERE id='{$task_id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

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

    public function post() {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
                ## task/<id>/attachment?filename&size [POST]
                if ( $this->getResourceNamePart( 2 ) == 'attachment' )
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

                ## task/<id>/comment [POST]: Добавляет корневой комментарий к задаче (он может быть только один)
                if ( $this->getResourceNamePart( 2 ) == 'comment' )
                {
                    $task_id = intval($this->getResourceNamePart( 1 ));

                    $query    = mysql_query( "SELECT createdby FROM `todo_task` WHERE id='{$task_id}'" ) or $this->throwMySQLError();
                    $task     = mysql_fetch_array($query, MYSQL_NUM);
                    $createdby  = $task[0];

                    if (empty($createdby))
                        $this->throwForbidden();

                    if (
                    !$this->loggedUser->hasPermission( User::PERMISSION_ADMINISTER )
                    && $this->loggedUser->getId() != $createdby )
                        $this->throwForbidden();

                    $query = mysql_query( "SELECT COUNT(*) as total FROM `todo_comment` WHERE `parentTask`='{$task_id}' AND `parentComment`='0'" );
                    $total = mysql_fetch_array( $query, MYSQL_NUM );
                    if ( $total[0] > 0 )
                        $this->throwForbidden();

                    $controllers_comment = new Controllers_comment($this->request);
                    $comment_id          = $controllers_comment->createComment($task_id, 0, $this->loggedUser->getId());

                    $this->response = array('id' => $comment_id);
                    $this->responseStatus = 202;
                }
                break;
        }
        return null;

    }
}

?>
