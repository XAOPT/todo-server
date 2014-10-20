<?php

class Controllers_convert extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'convert/users' => 'ConvertUsers',
                'convert/projects' => 'ConvertProjects'
            )
        );
    }

    public function ConvertUsers()
    {
        $query = mysql_query( "SELECT * FROM `user`" ) or $this->throwMySQLError();

        mysql_query("TRUNCATE TABLE `todo_user`");

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
                'tagcolor'   => $d['color'],
                'created'    => $d['created']
            );

            $this->insertArrayIntoDatabase('todo_project', $data);
        }

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }

    /*ublic function get()
    {
        $table = $this->getResourceNamePart( 1 );

        ##tasks
        if ($table == 'task')
        {
            $query = mysql_query( "SELECT * FROM `task`" ) or $this->throwMySQLError();

            $status_map = array('open', 'started', 'finished', 'closed', 'canceled', 'reopened');

            mysql_query("TRUNCATE TABLE `todo_task`");
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
            }

        }

        $this->response = 'ok';
    }*/
}

?>
