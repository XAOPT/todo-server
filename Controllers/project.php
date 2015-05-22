<?php

class Controllers_project extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'project'       => 'ProjectList'
            ),
            'put' => array(
                'project/(\d+)' => 'EditProject'
            ),
            'post' => array(
                'project' => 'CreateProject'
            )
        );
    }

    private function _morphTo($data = array())
    {
        if (isset($data['shorttitle'])) {
            $data['abc'] = $data['shorttitle'];
            unset($data['shorttitle']);
        }
        if (isset($data['tagcolor'])) {
            $data['color'] = $data['tagcolor'];
            $data['color'] = hexdec($data['color'])*-1;
            unset($data['tagcolor']);
        }

        return $data;
    }

    private function _morphFrom($data = array())
    {
        if (isset($data['abc'])) {
            $data['shorttitle'] = $data['abc'];
            unset($data['abc']);
        }
        if (isset($data['color'])) {
            $data['tagcolor'] = $data['color'];
            $data['tagcolor'] = dechex(abs($data['tagcolor']));
            unset($data['color']);
        }

        return $data;
    }

    public function checkProjectExists($project_id = 0)
    {
        $result = mysql_query( "SELECT * FROM `project` WHERE id='{$project_id}'" ) or $this->throwMySQLError();
        if (!mysql_num_rows($result))
            throw new Exception( 'Not Found', 404 );
    }

    public function ProjectList()
    {
        $id = $this->getRequestParamValue('id', false);
        $archived = intval($this->getRequestParamValue('archived', false));

        $where = array();

        if ($id) {
            if (is_array($id)) {
                foreach ($id as &$i) {
                    $i = intval($i);
                }

                $id = implode(",", $id);

                $where[] = "`id` IN ({$id})";
            }
            else {
                $id = intval($id);
                $where[] = "`id`={$id}";
            }
        }

        $where[] = "`archived`={$archived}";

        if (empty($where))
            $where[] = "1=1";

        $where = implode(" AND ", $where);

        ///

        $projects = array();

        $query = mysql_query( "SELECT *, UNIX_TIMESTAMP(created) AS created_unix FROM `project` WHERE {$where}" ) or $this->throwMySQLError();
        while( $db_project = mysql_fetch_array( $query ) )
        {
            $projects[] = $this->normalizeObject( $this->_morphFrom($db_project) );
        }

        $this->response = array(
            "status" => 0,
            "items" => $projects
        );
        $this->responseStatus = 200;
    }

    public function CreateProject()
    {
        /*if ( !$this->loggedUser->hasPermission( User::PERMISSION_PROJECT_MANAGEMENT ) )
            $this->throwForbidden();*/

        $data = $this->GetParamsFromRequestBody('create');

        $data = $this->_morphTo($data);

        $this->insertArrayIntoDatabase('project', $data);
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

        $data = $this->_morphTo($data);

        $this->UpdateDatabaseFromArray('project', $data, "id='{$project_id}'");

        $this->response = array(
            "status" => 0,
            "id" => $project_id
        );

        $this->responseStatus = 202; // accepted
    }
}

?>
