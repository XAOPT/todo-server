<?php

class Controllers_comment extends RestController
{
    function getCommentFromDatabase($comment_id)
    {
        $query = mysql_query( "SELECT * FROM `todo_comment` WHERE `id`='{$comment_id}'" ) or $this->throwMySQLError();

        $comment = mysql_fetch_array( $query );

        if (empty($comment))
            return null;

        $tmp = array(
            'id     '    => $comment['id'],
            'parentTask' => $comment['parentTask'],
            'parentComment' => $comment['parentComment'],
            'title'      => $comment['title'],
            'text'       => $comment['text'],
            'created'    => $comment['created'],
            'createdby'  => $comment['createdby'],
            'modified'   => $comment['modified'],
            'modifiedby' => $comment['modifiedby']
        );

        return $tmp;
    }

    function getCommentChildrens($comment_id = 0)
    {
        if (!$comment_id)
            return null;

        $query = mysql_query( "SELECT * FROM `todo_comment` WHERE `parentComment`='{$comment_id}'" ) or $this->throwMySQLError();

        $items = array();
        while ($comment = mysql_fetch_array( $query ))
        {
            $items[] = $comment;
        }

        return $items;
    }

    function createComment($parentTask = 0, $parentComment = 0, $user_id = 0)
    {
        $data = array(
            'parentTask'    => $parentTask,
            'parentComment' => $parentComment,
            'created'       => 'NOW()',
            'createdby'     => $user_id,
        );

        $this->insertArrayIntoDatabase('todo_comment', $data);

        $comment_id = mysql_insert_id();

        return $comment_id;
    }

    public function get()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            ## comment/<id> [GET]: Returns comment description.
            case 2:
                $comment_id = $this->getResourceNamePart( 1 );

                $comment = $this->getCommentFromDatabase( $comment_id );
                if ( !isset( $comment ) )
                    throw new Exception( 'Not Found', 404 );

                $this->response       = $comment;
                $this->responseStatus = 200;
                break;

            ## comment/<id>/comment [GET]: Returns description of all child comment.
            case 3:
                $comment_id = $this->getResourceNamePart( 1 );

                $result = mysql_query( "SELECT * FROM `todo_comment` WHERE id='{$comment_id}'" ) or $this->throwMySQLError();
                if (!mysql_num_rows($result))
                    throw new Exception( 'Not Found', 404 );

                $items = $this->getCommentChildrens($comment_id);

                $this->response       = $items;
                $this->responseStatus = 200;

                break;
        }

        return null;
    }

    public function post()
    {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
                ## comment/<id>/comment [POST]: Äîáàâëÿåò êîììåíòàðèé ê êîììåíòàðèþ.
                if ( $this->getResourceNamePart( 2 ) != 'comment' )
                    return null;

                $comment_id = $this->getResourceNamePart( 1 );

                $q = "
                SELECT t.project as project_id, t.assignee, c.parentTask
                FROM `todo_comment` AS c
                LEFT JOIN `todo_task` AS t ON (t.id=c.parentTask)
                WHERE c.id='{$comment_id}'";

                $query    = mysql_query( $q ) or $this->throwMySQLError();
                $project  = mysql_fetch_array($query, MYSQL_ASSOC);

                if (empty($project) || !$project['project_id'])
                    throw new Exception( 'Not Found', 404 );

                if (
                !$this->loggedUser->hasProjectPermission( User::PERMISSION_COMMENT_MANAGEMENT, $project['project_id'] )
                && !$this->loggedUser->hasProjectPermission( User::PERMISSION_TESTER, $project['project_id'] )
                && $this->loggedUser->getId() != $project['assignee'] )
                    $this->throwForbidden();

                $comment_id = $this->createComment($project['parentTask'], $comment_id, $this->loggedUser->getId());

                $this->response = array('id' => $comment_id);
                $this->responseStatus = 201;

                break;
        }
    }

    public function put()
    {
        ## comment/<id> [PUT]
        if ( $this->getResourceNamePartsCount() != 2 )
            return null;

        $comment_id = intval($this->getResourceNamePart( 1 ));

        $q = "
        SELECT t.project as project_id, c.createdby
        FROM `todo_comment` AS c
        LEFT JOIN `todo_task` AS t ON (t.id=c.parentTask)
        WHERE c.id='{$comment_id}'";

        $query    = mysql_query( $q ) or $this->throwMySQLError();
        $project  = mysql_fetch_array($query, MYSQL_ASSOC);

        if (empty($project) || !$project['project_id'])
            throw new Exception( 'Not Found', 404 );

        if (
        !$this->loggedUser->hasProjectPermission( User::PERMISSION_COMMENT_MANAGEMENT, $project['project_id'] )
        && $this->loggedUser->getId() != $project['createdby'] )
            $this->throwForbidden();

        $editable_columns = array(
            'title'=>'title',
            'text'=>'text'
        );

        $sets = $this->GetEditablesFromBody($editable_columns);
        $sets[] = 'modified=NOW()';
        $sets[] = "modifiedby='".$this->loggedUser->getId()."'";
        $sets = implode(',', $sets);

        mysql_query( "UPDATE `todo_comment` SET {$sets} WHERE id='{$comment_id}'" ) or $this->throwMySQLError();

        $this->response = 'ok';
        $this->responseStatus = 202;

        return null;
    }

   /* public function post() {
        switch ( $this->getResourceNamePartsCount() )
        {
            case 3:
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

    }*/


}

?>
