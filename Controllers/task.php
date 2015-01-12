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
                'task/\d+' => 'DeleteTask',
                'task/attachment' => 'DeleteAttachment',
            )
        );
    }

    public function checkTaskExists($id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_task` WHERE id='{$id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    public function GetTasks()
    {
        $id        = intval($this->getRequestParamValue('id', false));
        $project   = $this->getRequestParamValue('project', false);
        $title     = $this->getRequestParamValue('title', false);
        $text      = $this->getRequestParamValue('text', false);
        $assignee  = $this->getRequestParamValue('assignee', false);
        $status    = $this->getRequestParamValue('status', false);
        $priority  = $this->getRequestParamValue('priority', false);
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
            $project = implode(",", $project);
            $where[] = "`project` IN ({$project})";
        }
        if ($title) {
            $where[] = "`title` LIKE '%{$title}%'";
        }
        if ($text) {
            $where[] = "`text` LIKE '%{$text}%'";
        }
        if ($assignee) {
            if (is_array($assignee)) {
                $assignee = implode(",", array_map('intval', $assignee));
                $where[] = "`assignee` IN ({$assignee})";
            }
            else {
                $assignee = intval($assignee);
                $where[] = "`assignee`={$assignee}";
            }
        }
        if ($status) {
            $status = implode("','", $status);
            $where[] = "`status` IN ('{$status}')";
        }
        if ($priority) {
            if (is_array($priority)) {
                $priority = implode(",", array_map('intval', $priority));
                $where[] = "`priority` IN ({$priority})";
            }
            else {
                $priority = intval($priority);
                $where[] = "`priority`={$priority}";
            }
        }
        if ($parent) {
            $where[] = "`parentTask`={$parent}";
        }

        if (empty($where))
            throw new Exception('Search params are empty', 400);

        $where = implode(" AND ", $where);

        $items = array();
        $query = mysql_query("SELECT * FROM `todo_task` WHERE {$where} ORDER BY priority DESC LIMIT {$from}, {$count}") or $this->throwMySQLError();

        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $dbtask );
        }

        // если запрашивается описание какой-то одной конкретной задачи и она найдена - получим список прикреплённых файлов
        if ($id && isset($items[0]))
        {
            $items[0]['attachments'] = array();

            $query = mysql_query("SELECT * FROM `todo_attachment` WHERE task={$id}") or $this->throwMySQLError();
            while( $result = mysql_fetch_array( $query ) )
            {
                $attachment = $this->normalizeObject( $result, 'attachment' );
                $attachment['url'] = "/attachments/" . $attachment['filename'];

                $items[0]['attachments'][] = $attachment;
            }
        }
        ///////////

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

        if (!isset($data['status'])) {
            $data['status'] = "open";
        }

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

        mysql_query("DELETE FROM `todo_microtask` WHERE taskid='{$task_id}'");
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

        $file_name = mysql_real_escape_string($_FILES['file']['name']);
        $file_name = preg_replace("/[^a-zA-Z0-9\.]/", "", $file_name);

        $allowed_extensions = array("doc", "txt", "rtf", "docx", "csv", "xml", "xss", "gif", "jpg", "png", "jpe", "jpeg");

        $file_name_arr = explode(".", $file_name);
        $file_type     = strtolower(end($file_name_arr));

        if (!in_array(strtolower($file_type), $allowed_extensions))
            throw new Exception("file type is not allowed", 406);

        // найдём свободное имя файла
        $free_name = false;
        $i = 0;
        $original_filename = $file_name;
        while (!$free_name)
        {
            if (file_exists(APPLICATION_PATH.'/attachments/'.$file_name))
            {
                $i++;
                $file_name = $i."_".$original_filename;
            }
            else
                $free_name = true;
        }

        $uploaded = move_uploaded_file($_FILES['file']['tmp_name'], APPLICATION_PATH.'/attachments/'.$file_name);

        if ($uploaded)
        {
            $size = $_FILES['file']['size'];
            mysql_query("INSERT INTO `todo_attachment` (task, filename, size) VALUES ('{$task_id}', '{$file_name}', '{$size}')") or $this->throwMySQLError();
            $attach_id = mysql_insert_id();
        }

        $this->response = array(
            "status" => 0,
            'id' => $attach_id
        );
        $this->responseStatus = 200;
    }

    function DeleteAttachment()
    {
        $id = $this->getRequestBodyValue('id', true);

        $query = mysql_query("SELECT * FROM `todo_attachment` WHERE id='{$id}'") or $this->throwMySQLError();
        $attachment = mysql_fetch_assoc($query);

        if (!empty($attachment)) {
            unlink(APPLICATION_PATH.'/attachments/'.$attachment['filename']);
            mysql_query("DELETE FROM `todo_attachment` WHERE id='{$id}'");
        }

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }
}

?>
