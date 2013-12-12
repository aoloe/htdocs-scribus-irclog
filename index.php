<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (!function_exists('debug')) {
    function debug($label, $value) {
        echo("<p>$label<br /><pre>".htmlentities(print_r($value, 1))."</pre></p>");
    }
}

$username = null;
$password = null;

// debug('_COOKIE', $_COOKIE);
if (array_key_exists('scribus_username' ,$_COOKIE)) {
    $username = $_COOKIE['scribus_username'];
}

if (array_key_exists('scribus_password' ,$_COOKIE)) {
    $password = $_COOKIE['scribus_password'];
}

if (array_key_exists('ok' ,$_REQUEST)) {
    if ($_REQUEST['username'] != '') {
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        $expiration = time()+60*60*24*30*12;
        setcookie("scribus_username", $username, $expiration);
        setcookie("scribus_password", $password, $expiration);
    }
}

if (array_key_exists('days', $_REQUEST) && ($_REQUEST['days'] !== '')) {
    $days = $_REQUEST['days'];
} else {
    $days = 1;
}

?>
<!DOCTYPE HTML>
<html>
<body>
<form method="post">
username / password <input type="input" name="username" />
<input type="password" name="password" /><br />
days <input type="days" name="days" /><br />
<input type="submit" name="ok" value="&raquo;"/>
</form>

<?php

if (isset($username)) {

    // debug('date', $date);

    $url = array (
        'scribus' => array (
            'channel' => '%23scribus',
            'title' => 'Scribus',
            'archived' => true,
        ),
        'lgm' => array (
            'channel' => '%23lgm',
            'title' => 'LGM',
            'archived' => false,
        ),
    );

    $date = get_date($days);
    // debug('date', $date);

    foreach ($url as $key => $value) {
        echo("<h2>".$value['title']."</h2>");
        $url = 'http://irclogs.scribus.net/'.$value['channel'].'/';
        $remaining_items = count($date);
        // debug('remaining_items', $remaining_items);
        foreach ($date as $item) {
            $uri = get_uri($item, $value['channel'], (0 !== --$remaining_items) ? $value['archived'] : false);
            // debug('remaining_items', $remaining_items);
            $page = get_page($url, $uri, $username, $password);
            // $value['title'], 
            render_page($item, $page);
        }
        unset($page);
    }
}

function get_date($days) {
    $result = array();
    // debug('days', $days);

    $now = new DateTime();
    // debug('now', $now);
    $result[] = clone $now;

    for ($i = 0; $i < $days; $i++) {
        $now->sub(new DateInterval('P1D'));
        $result[] = clone $now;
    }
    $result = array_reverse($result);
    // debug('result', $result);

    return $result;
} // get_date()

function get_uri($date, $channel, $archived) {
    $result =  strtr(
        (
            $archived ?
            '$Y/$Y-$m/$channel.$Y-$m-$d.log' :
            '$channel.$Y-$m-$d.log'
        ),
        array(
            '$channel' => $channel,
            '$Y' => $date->format('Y'),
            '$m' => $date->format('m'),
            '$d' => $date->format('d'),
        )
    );
    // debug('result', $result);
    return $result;
} // get_uri()

function get_page($url, $uri, $username, $password) {
    $result = '';

    // debug('url', $url.$uri);

    $curl = curl_init($url.$uri);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);                         
    curl_setopt($curl, CURLOPT_USERPWD, $username.':'.$password);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);                    
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);                          
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);                           
    curl_setopt($curl, CURLOPT_USERAGENT, 'aoloe/scribus-irc');

    $response = curl_exec($curl);                                          
    $resultStatus = curl_getinfo($curl);                                   

    if($resultStatus['http_code'] == 200) {
        $result = $response;
    } else {
        debug('Call Failed ',$resultStatus);
    }
    // debug('result', $result);

    return $result;
} // get_page()

function render_page($date, $page) {
    // debug('page', $page);
    $page = explode("\n", $page);
    // debug('date', $date->format('d.m.Y'));
    // debug('page', $page);
    $chat = array();
    foreach ($page as $item) {
        $time = trim(substr($item, 0, 21));
        // debug('time', $time);
        // debug('substr', substr($item, 21, 3));
        if (($item != '') && (substr($item, 21, 3) != '***')) {
            // debug('item', $item);
            // debug('substr', substr($item, 21));
            $message = substr($item, 21);
            // debug('message', $message);
            $nick_end = strpos($message, '>');
            // debug('nick_end', $nick_end);
            $nick = trim(substr($message, 1, $nick_end - 1));
            // debug('nick', $nick);
            $message = trim(substr($message, $nick_end + 1));
            // debug('message', $message);
            $chat[] = strtr(
                '$nick ($time) $message',
                array(
                    '$time' => substr($time, 11, 5),
                    '$nick' => $nick,
                    '$message' => htmlentities($message),
                )
            );
        }
    }

    if (count($chat) == 0) {
        echo(strtr(
            '<h3>$date</h3>'."\n".'<p>No entries</p>'."\n",
            array (
                '$date' => $date->format('d.m.Y')
            )
        ));
    } else {
        echo(strtr(
            '<h3>$date</h3>'."\n".'$chat',
            array (
                '$date' => $date->format('d.m.Y'),
                '$chat' => "<p>".implode("<br />\n", $chat)."</p>\n"
            )
        ));
    }

} // render_page()

?>
</body>
</html>
