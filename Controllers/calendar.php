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

    private function _morphTo($data = array())
    {
        if (isset($data['userid'])) {
            $data['uid'] = $data['userid'];
        }
        if (isset($data['kind'])) {
            $data['dayoff'] = ($data['kind'] == 'dayoff')?'Y':'N';
        }
        if (isset($data['day'])) {
            $data['date'] = date("Y-m-d", $data['day']*86400+3600*12);
        }

        return $data;
    }

    private function _morphFrom($data = array())
    {
        if (isset($data['uid'])) {
            $data['userid'] = $data['uid'];
        }
        if (isset($data['dayoff'])) {
            $data['kind'] = ($data['dayoff'] == 'Y')?'dayoff':'workday';
        }
        if (isset($data['date'])) {
            $data['day'] = floor(strtotime("{$data['date']} 12:00:00")/86400);
        }

        return $data;
    }

    public function GetCalendar()
    {
        $userid  = intval($this->getRequestParamValue('userid', false));

        $from  = intval($this->getRequestParamValue('from', false));
        $count = intval($this->getRequestParamValue('count', false));

        if (!$from) $from = 0;
        if (!$count) $count = 100;

        if (!$userid) $userid = 0;

        $to = $from + $count;

        $from_date = date("Y-m-d", $from*86400+3600*12);
        $to_date = date("Y-m-d", $to*86400+3600*12);

        $items = array();
        $query = mysql_query("SELECT * FROM `calendar` WHERE uid={$userid} AND date >= '{$from_date}' AND date <= '{$to_date}'") or $this->throwMySQLError();

        while( $obj = mysql_fetch_array( $query ) )
        {
            $items[] = $this->normalizeObject( $this->_morphFrom($obj) );
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
        $data = $this->GetParamsFromRequestBody('edit');

        $data = $this->_morphTo($data);

        //mysql_query("INSERT INTO `calendar` (day, userid, kind) VALUES ({$data['day']}, {$data['userid']}, '{$data['kind']}') ON DUPLICATE KEY UPDATE kind='{$data['kind']}';") or $this->throwMySQLError();
        mysql_query("INSERT INTO `calendar` (`date`, uid, dayoff) VALUES ('{$data['date']}', {$data['uid']}, '{$data['dayoff']}') ON DUPLICATE KEY UPDATE dayoff='{$data['dayoff']}'") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );

        $this->responseStatus = 202;
    }

    public function RemoveCalendarCell()
    {
        $userid = intval($this->getRequestParamValue('userid', false));
        $day    = intval($this->getRequestParamValue('day', true));

        if (!$userid) $userid = 0;

        mysql_query("DELETE FROM `calendar` WHERE day='{$day}' AND userid='{$userid}'") or $this->throwMySQLError();

        $this->response = array(
            "status" => 0
        );
        $this->responseStatus = 200;
    }
}

?>
