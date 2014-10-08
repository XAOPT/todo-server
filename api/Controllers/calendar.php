<?php

class Controllers_calendar extends RestController
{
    public function routes()
    {
        return array(
            'get' => array(
                'calendar' => 'GetCalendar'
            ),
            'put' => array(
                'calendar' => 'EditCalendar'
            ),
            'delete' => array(
                'calendar' => 'RemoveCalendarCell'
            )
        );
    }

    public function GetCalendar()
    {
        $userid  = intval($this->getRequestParamValue('userid', false));

        $from  = intval($this->getRequestParamValue('from', false));
        $count = intval($this->getRequestParamValue('count', false));

        if (!$from) $from = 0;
        if (!$count) $count = 100;

        if (!$userid) $userid = 0;

        $items = array();
        $query = mysql_query("SELECT * FROM `todo_calendar` WHERE userid={$userid} AND day >= {$from} LIMIT 0, {$count}") or $this->throwMySQLError();

        while( $obj = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $obj );
        }

        $this->response = array(
            "status" => 0,
            "from" => $from,
            "count" => $count,
            "items" => $items
        );
        $this->responseStatus = 200;
    }

    public function EditCalendar()
    {
        $userid  = intval($this->getRequestBodyValue('userid', false));
        $day     = intval($this->getRequestBodyValue('day', true));
        $kind    = $this->getRequestBodyValue('kind', true);

        mysql_query("INSERT INTO `todo_calendar` (day, userid, kind) VALUES ({$day}, {$userid}, '{$kind}') ON DUPLICATE KEY UPDATE kind='{$kind}';") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );

        $this->responseStatus = 202;
    }

    public function RemoveCalendarCell()
    {
        $userid  = intval($this->getRequestParamValue('userid', false));
        $day     = intval($this->getRequestParamValue('day', true));

        if (!$userid) $userid = 0;

        mysql_query("DELETE FROM `todo_calendar` WHERE day='{$day}' AND userid='{$userid}'") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }
}

?>
