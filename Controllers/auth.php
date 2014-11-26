<?php

class Controllers_auth extends RestController
{
    public function routes()
    {
        return array(
            'post' => array(
                'auth' => 'Auth',
                'auth/fb' => 'AuthViaFacebook'
            )
        );
    }

    public function getToken($userid = '')
    {
        if (!$userid)
            return null;

        // создаем новую сессию для данного юзера.
        mysql_query( "DELETE FROM `todo_session` WHERE userid={$userid}" ) or $this->throwMySQLError();

        $auth_token = md5( microtime() );
        $ipaddr     = (string)$_SERVER['REMOTE_ADDR'];
        $user_agent = (string)$_SERVER['HTTP_USER_AGENT'];

        mysql_query( "INSERT INTO `todo_session` (auth_token, userid, ipaddr, user_agent) VALUES('{$auth_token}',{$userid},'{$ipaddr}','{$user_agent}')" ) or $this->throwMySQLError();

        return $auth_token;
    }

    // дополнительная степень защиты
    private function getIdFromSignedRequest($signed_request) {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        $secret = "b9390a61f010c38ecfbaf20e4475500a"; // Use your app secret here

        // decode the data
        $sig = $this->base64_url_decode($encoded_sig);
        $data = json_decode($this->base64_url_decode($payload), true);

        // confirm the signature
        $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
        if ($sig !== $expected_sig) {
            return null;
        }

        return $data['user_id'];
    }

    private function base64_url_decode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    public function AuthViaFacebook()
    {
        $accessToken = $this->getRequestBodyValue('accessToken', true);
        $signedRequest = $this->getRequestBodyValue('signedRequest', true);

        $signed_id = $this->getIdFromSignedRequest($signedRequest);

        // получим данные о ФБ-пользователе
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "https://graph.facebook.com/v2.2/me?access_token=".$accessToken,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ));

        $data = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($data, true);

        if (!$data || !isset($data['id']) || $signed_id != $data['id'])
            throw new Exception( 'Not Found', 404 );

        $query = mysql_query( "SELECT * FROM `todo_user` WHERE fbid='{$data['id']}'" ) or $this->throwMySQLError();

        // если пользователь с таким фейсбучным айди уже существует
        if ( $user = mysql_fetch_assoc( $query ) ) {
            $auth_token = $this->getToken($user['id']);

            $this->response = array(
                'status' => 0,
                'auth_token' => $auth_token,
                'userid' => $user['id']
            );

            $this->responseStatus = 200;
            return;
        }

        // поищем пользователя по почтовому адресу, стянутого с фб
        if (isset($data['email'])) {
            $query = mysql_query( "SELECT * FROM `todo_user` WHERE email='{$data['email']}'" ) or $this->throwMySQLError();

            if ($user = mysql_fetch_assoc( $query )) {

                $insert = array(
                    "fbid" => $data['id']
                );

                if (!$user['firstname'] && isset($data['first_name'])) {
                    $insert['firstname'] = $data['first_name'];
                }
                if (!$user['lastname'] && isset($data['last_name'])) {
                    $insert['lastname'] = $data['last_name'];
                }
                // пользователь с такой почтой найден
                $this->UpdateDatabaseFromArray('todo_user', $insert, "id={$user['id']}");

                $auth_token = $this->getToken($user['id']);

                $this->response = array(
                    'status' => 0,
                    'auth_token' => $auth_token,
                    'userid' => $user['id']
                );

                $this->responseStatus = 200;
                return;
            }
        }

        // ничего не нашлось - вернём ошибку
        throw new Exception( 'Not Found', 404 );
    }

    public function Auth()
    {
        $email    = mysql_real_escape_string($this->getRequestBodyValue('email', true));
        $password = md5($this->getRequestBodyValue('pwd', true));

        $query = mysql_query( "SELECT * FROM `todo_user` WHERE email='{$email}' AND password='{$password}'" ) or $this->throwMySQLError();

        if ( $user = mysql_fetch_assoc( $query ) ) {
            $auth_token = $this->getToken($user['id']);

            $this->response = array(
                'status' => 0,
                'auth_token' => $auth_token,
                'userid' => $user['id']
            );
        }
        else
            throw new Exception( 'Not Found', 404 );

        $this->responseStatus = 200;
    }

    public function checkAuth() {
        return true;
    }
}

?>
