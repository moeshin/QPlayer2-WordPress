<?php


class QPlayer2_Ajax
{
    public function __construct($options)
    {
        $server = $this->get('server');
        $type = $this->get('type');
        $id = $this->get('id');

        if (!$this->test($server, $type, $id)) {
            http_response_code(403);
            die();
        }

        include_once 'libs/Meting.php';
        $m = new Metowolf\Meting($server);
        $m->format(true);

        $cookie = $options['cookie'];
        if ($server == 'netease' && !empty($cookie)) {
            $m->cookie($cookie);
        }

        $arg2 = null;
        switch ($type) {
            case 'audio':
                $type = 'url';
                $arg2 = $options['bitrate'];
                break;
            case 'cover':
                $type = 'pic';
                $arg2 = 64;
                break;
            case 'lrc':
                $type = 'lyric';
                break;
            case 'artist':
                $arg2 = 50;
                break;
        }
        $data = $m->$type($id, $arg2);
        $data = json_decode($data, true);
        switch ($type) {
            case 'url':
            case 'pic':
                $url = $data['url'];
                if (empty($url)) {
                    if ($server != 'netease') {
                        http_response_code(403);
                        die();
                    }
                    $url = 'https://music.163.com/song/media/outer/url?id=' . $id . '.mp3';
                } else {
                    $url = preg_replace('/^http:/', 'https:', $url);
                }
                $data = $url;
                break;
            case 'lyric':
                $data = $data['lyric'] . "\n" . $data['tlyric'];
                break;
            default:
                $url = admin_url('admin-ajax.php?action=QPlayer2');
                $array = array();
                foreach ($data as $v) {
                    $prefix = $url . '&server=' . $v['source'];
                    $array []= array(
                        'name' => $v['name'],
                        'artist' => implode(' / ', $v['artist']),
                        'audio' => $prefix . '&type=audio&id=' . $v['url_id'],
                        'cover' => $prefix . '&type=cover&id=' . $v['pic_id'],
                        'lrc' => $prefix . '&type=lrc&id=' . $v['lyric_id'],
                        'provider' => 'default'
                    );
                }
                $data = json_encode($array);
        }

        switch ($type) {
            case 'url':
            case 'pic':
            case 'audio':
            case 'cover':
                header('Location: ' . $data, false, 301);
                break;
            case 'lrc':
            case 'lyric':
                header("Content-Type: text/plain");
                break;
            default:
                header("Content-Type: application/json");
                break;
        }
        echo $data;
    }

    private function get($key) {
        $r = $_REQUEST[$key];
        return isset($r) ? $r : null;
    }

    private function test($server, $type, $id)
    {
        if (!in_array($server, array('netease', 'tencent', 'baidu', 'xiami', 'kugou'))) {
            return false;
        }
        if (!in_array($type, array('audio', 'cover', 'lrc', 'song', 'album', 'artist', 'playlist'))) {
            return false;
        }
        if (empty($id)) {
            return false;
        }
        return true;
    }
}