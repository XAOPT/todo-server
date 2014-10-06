<?php

class Controllers_project extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'project'              => 'FullProjectList',
                'project/(\d+)'        => 'GetProject'
            ),
            'put' => array(
                'project/(\d+)' => 'EditProject'
            ),
            'post' => array(
                'project' => 'CreateProject'
            )
        );
    }

    public function getProjectFromDatabase( $proj_id )
    {
        $query = mysql_query( "SELECT *, UNIX_TIMESTAMP(created) AS created_unix FROM `todo_project` WHERE `id`='$proj_id'" ) or $this->throwMySQLError();

        if ( $project = mysql_fetch_array( $query ) )
            return $project;

        return null;
    }

    public static function createProjectFromDatabaseObject( $dbobj )
    {
        if ( !isset( $dbobj ) )
            return null;

        $proj = array(
            'class'       => 'project',
            'id'          => (int)$dbobj['id'],
            'archived'    => (bool)false,
            'title'       => (string)$dbobj['title'],
            'shorttitle'  => (string)$dbobj['shorttitle'],
            'tagcolor'    => (int)$dbobj['tagcolor'],
            'created'     => (string)$dbobj['created_unix']
            );

        return $proj;
    }

    public function checkProjectExists($project_id = 0)
    {
        $result = mysql_query( "SELECT * FROM `todo_project` WHERE id='{$project_id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    // project [GET]: Return all projects.
    public function FullProjectList()
    {
        $projects = array( 'class' => 'projectList' );

        $items = array();
        $query = mysql_query( "SELECT *, UNIX_TIMESTAMP(created) AS created_unix FROM `todo_project`" ) or $this->throwMySQLError();
        while( $db_project = mysql_fetch_array( $query ) )
        {
            $items[] = $this->createProjectFromDatabaseObject( $db_project );
        }

        $projects['items'] = $items;

        $this->response = $projects;
        $this->responseStatus = 200;
    }

    // project/(\d+) [GET]: Returns full project description.
    public function GetProject()
    {
        $project_id = $this->getResourceNamePart( 1 );

        $db_project = $this->getProjectFromDatabase( $project_id );

        if ( !isset( $db_project ) )
            throw new Exception( 'Not Found', 404 );

        $this->response = $this->createProjectFromDatabaseObject($db_project);

        $this->responseStatus = 200;
    }

    // project [POST]: Create project
    public function CreateProject()
    {
        if ( !$this->loggedUser->hasPermission( User::PERMISSION_PROJECT_MANAGEMENT ) )
            $this->throwForbidden();

        $title = $this->getRequestBodyValue( 'title' );

        $data = array(
            'title'     => $title,
            'created'   => 'NOW()',
            'createdby' => $this->loggedUser->getId()
        );

        $this->insertArrayIntoDatabase('todo_user', $data);
        $id = mysql_insert_id();

        $this->response = array('id' => $id);
        $this->responseStatus = 201; // created
    }

    ## project/<id> [PUT]: Modify project
    public function EditProject()
    {
        if ( !$this->loggedUser->hasPermission( User::PERMISSION_PROJECT_MANAGEMENT ) )
            $this->throwForbidden();

        $project_id = $this->getResourceNamePart( 1 );

        $this->checkProjectExists($project_id);

        $editable_columns = array('title'=>'title', 'shorttitle'=>'shorttitle', 'tagcolor'=>'tagcolor');

        $sets = $this->GetEditablesFromBody($editable_columns);
        $sets = implode(',', $sets);

        if (!empty($sets))
            mysql_query( "UPDATE `todo_project` SET {$sets} WHERE id='{$project_id}'" ) or $this->throwMySQLError();

        $this->response = array("status" => 0);
        $this->responseStatus = 202; // accepted
        break;
    }

    /*public function get()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            // project/<id>/task [GET]: Returns all project root tasks.
            case 3:
                if ( $this->getResourceNamePart( 2 ) != 'task' )
                    return null;

                $project_id = $this->getResourceNamePart( 1 );

                $this->checkProjectExists( $project_id );

                $task_controller = new Controllers_task($this->request);
                $items = $task_controller->GetProjectTasks($project_id);

                $this->response = $items;
                $this->responseStatus = 200;

                break;
        }

        return null;
    }*/

   /* public function post()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
                ## project/<id>/task [POST]: Create new task
                if ( $this->getResourceNamePart( 2 ) == 'task' )
                {
                    $project_id = $this->getResourceNamePart( 1 );
                    $title      = $this->getRequestBodyValue( 'title' );

                    if ( !$this->loggedUser->hasProjectPermission( User::PERMISSION_TASK_MANAGEMENT, $project_id ) )
                        $this->throwForbidden();


                    $this->checkProjectExists($project_id);

                    $data = array(
                        'title'     => $title,
                        'project'   => $project_id,
                        'created'   => 'NOW()',
                        'createdby' => $this->loggedUser->getId()
                    );

                    $task_controller = new Controllers_task();
                    $task_id = $task_controller->createTaskInDatabase($data);

                    $this->response = array('id' => $task_id);
                    $this->responseStatus = 201;
                }

                break;
        }
    }*/
}

?>
