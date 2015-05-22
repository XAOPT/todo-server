<?php

class Controllers_microtask extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'microtask' => 'GetMicrotasks'
            ),
            'post' => array(
                'microtask' => 'CreateMicrotask'
            ),
            'put' => array(
                'microtask/\d+' => 'EditMicrotask'
            ),
            'delete' => array(
                'microtask/\d+' => 'DeleteMicrotask'
            )
        );
    }

    public function GetMicrotasks()
    {
        $taskid  = intval($this->getRequestParamValue('taskid', true));

        $items = array();
        /*$query = mysql_query("SELECT * FROM `todo_microtask` WHERE taskid={$taskid}") or $this->throwMySQLError();

        while( $dbtask = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $dbtask );
        }*/

        $this->response = array(
            "status" => 0,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function CreateMicrotask()
    {
        $data = $this->GetParamsFromRequestBody('create');

        $data['status'] = "open";

        $this->insertArrayIntoDatabase('todo_microtask', $data);
        $id = mysql_insert_id();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
        $this->responseStatus = 201;
    }

    public function EditMicrotask()
    {
        $task_id = intval($this->getResourceNamePart( 1 ));

        $data = $this->GetParamsFromRequestBody('edit');

        $this->UpdateDatabaseFromArray('todo_microtask', $data, "id={$task_id}");

        $this->response = array(
            "status" => 0,
            "id" => $task_id
        );

        $this->responseStatus = 202; // accepted
    }

    public function DeleteMicrotask()
    {
        $id = intval($this->getResourceNamePart( 1 ));

        mysql_query("DELETE FROM `todo_microtask` WHERE id={$id}");

        $this->response = array("status" => 0);
        $this->responseStatus = 200;

        return;
    }
}

?>
