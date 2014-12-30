<?php

class Controllers_comment extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'comment' => 'GetComments'
            ),
            'post' => array(
                'comment' => 'CreateComment'
            ),
            'put' => array(
                'comment/\d+' => 'EditComment'
            ),
        );
    }

    public function checkCommentExists($id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_comment` WHERE id='{$id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    public function GetComments()
    {
        $taskid = intval($this->getRequestParamValue('taskid', true));

        $query = mysql_query("SELECT * FROM `todo_comment` WHERE taskid={$taskid} ORDER BY id") or $this->throwMySQLError();

        $items = array();
        while( $row = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $row );
        }

        $this->response = array(
            "status" => 0,
            "taskid" => $taskid,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function EditComment()
    {
        $id = intval($this->getResourceNamePart( 1 ));

        $this->checkCommentExists($id);

        $data = $this->GetParamsFromRequestBody('edit');

        $data['modified'] = 'NOW()';
        $data['modifiedby'] = $this->loggedUser->getId();

        $this->UpdateDatabaseFromArray('todo_comment', $data, "id='{$id}'");

        $this->response = array(
            "status" => 0,
            "id" => $id
        );

        $this->responseStatus = 202;
    }

    public function CreateComment()
    {
        $data = $this->GetParamsFromRequestBody('create');

        Controllers_task::checkTaskExists($data['taskid']);

        $data['modifiedby'] = $this->loggedUser->getId();

        $this->insertArrayIntoDatabase('todo_comment', $data);
        $id = mysql_insert_id();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
        $this->responseStatus = 201;
    }
}

?>
