<?php

class Controllers_project extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'project'       => 'ProjectList',
                'project/(\d+)' => 'GetProject'
            ),
            'put' => array(
                'project/(\d+)' => 'EditProject'
            ),
            'post' => array(
                'project' => 'CreateProject'
            )
        );
    }

    public function checkProjectExists($project_id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_project` WHERE id='{$project_id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }


    public function ProjectList()
    {
        // условия поиска
        $archived = intval($this->getRequestParamValue('archived', false));

        $where = array();

        $where[] = "`archived`={$archived}";

        if (empty($where))
            $where[] = "1=1";

        $where = implode(" AND ", $where);

        ///

        $projects = array();

        $query = mysql_query( "SELECT *, UNIX_TIMESTAMP(created) AS created_unix FROM `todo_project` WHERE {$where}" ) or $this->throwMySQLError();
        while( $db_project = mysql_fetch_array( $query ) )
        {
            $projects[] = $this->normalizeObject( $db_project );
        }

        $this->response = array(
            "status" => 0,
            "items" => $projects
        );
        $this->responseStatus = 200;
    }

    public function GetProject($project_id = null)
    {
        if (!$project_id)
            $project_id = intval($this->getResourceNamePart( 1 ));

        $query = mysql_query( "SELECT *, UNIX_TIMESTAMP(created) AS created_unix FROM `todo_project` WHERE `id`={$project_id}" ) or $this->throwMySQLError();

        $project = mysql_fetch_array( $query );

        if ( !isset( $project ) )
            throw new Exception( 'Not Found', 404 );

        $this->response = array(
            "status" => 0,
            "items" => array($this->normalizeObject( $project ))
        );

        $this->responseStatus = 200;
    }

    public function CreateProject()
    {
        /*if ( !$this->loggedUser->hasPermission( User::PERMISSION_PROJECT_MANAGEMENT ) )
            $this->throwForbidden();*/

        $data = $this->GetParamsFromRequestBody('create');

        $this->insertArrayIntoDatabase('todo_project', $data);
        $id = mysql_insert_id();

        $this->response = array(
            "status" => 0,
            "id" => $id
        );
        $this->responseStatus = 201;
    }

    public function EditProject()
    {
        /*if ( !$this->loggedUser->hasPermission( User::PERMISSION_PROJECT_MANAGEMENT ) )
            $this->throwForbidden();*/

        $project_id = $this->getResourceNamePart( 1 );

        $this->checkProjectExists($project_id);

        $data = $this->GetParamsFromRequestBody('edit');

        $this->UpdateDatabaseFromArray('todo_project', $data, "id='{$project_id}'");

        $this->response = array(
            "status" => 0,
            "id" => $project_id
        );

        $this->responseStatus = 202; // accepted
    }
}

?>
