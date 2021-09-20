<?php
/*
Plugin Name: QPlayer2
Description: 一款简洁小巧的 HTML5 底部悬浮音乐播放器
Author: MoeShin
Version: 2.0.7
Author URI: https://moeshin.com/
*/

use QPlayer\Cache\Cache;

class QPlayer2
{
    const OPTION_NAME = 'QPlayer2_options';
    const OPTION_GROUP = 'QPlayer2_group';
    const PAGE = 'QPlayer2_settings';
    const SECTION = 'QPlayer2_section';

    private $options;

    public function __construct()
    {
        if (is_admin()) {
            $this->onAdmin();
        }

        add_action('wp_footer', array($this, 'footer'));
        add_action('wp_ajax_QPlayer2', array($this, 'ajax'));
        add_action('wp_ajax_nopriv_QPlayer2', array($this, 'ajax'));

        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function deactivate()
    {
        $options = $this->options;
        $cacheType = $options['cacheType'];
        if ($cacheType != 'none') {
            $this->requireCache();
            Cache::UninstallWithOptions($options);
        }
        delete_option(QPlayer2::OPTION_NAME);
    }

    private function initOptions()
    {
        if ($this->options != null) {
            return;
        }
        $options = get_option(self::OPTION_NAME, $this->getDefault());
        $this->options = is_array($options) ? $options : array();
    }

    private function getDefault()
    {
        $names = array(
            'cdn',
            'isRotate',
            'isShuffle',
            'isPauseOtherWhenPlay',
            'isPauseWhenOtherPlay'
        );
        $r = array(
            'bitrate' => '320',
            'color' => '#EE1122',
            'list' => '[{
    "name": "Nightglow",
    "artist": "蔡健雅",
    "audio": "https://cdn.jsdelivr.net/gh/moeshin/QPlayer-res/Nightglow.mp3",
    "cover": "https://cdn.jsdelivr.net/gh/moeshin/QPlayer-res/Nightglow.jpg",
    "lrc": "https://cdn.jsdelivr.net/gh/moeshin/QPlayer-res/Nightglow.lrc"
},
{
    "name": "やわらかな光",
    "artist": "やまだ豊",
    "audio": "https://cdn.jsdelivr.net/gh/moeshin/QPlayer-res/やわらかな光.mp3",
    "cover": "https://cdn.jsdelivr.net/gh/moeshin/QPlayer-res/やわらかな光.jpg"
}]',
            'cacheType' => 'none'
        );
        foreach ($names as $name) {
            $r[$name] = true;
        }
        return $r;
    }

    public function onAdmin()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
    }

    public static function plugin_action_links($links)
    {
        $link = '<a href="' . esc_url(admin_url('options-general.php?page=QPlayer2')) . '">' . __('设置', 'QPlayer2') . '</a>';
        array_unshift($links, $link);
        return $links;
    }

    public function add_plugin_page()
    {
        add_options_page(
            "QPlayer2",
            "QPlayer2",
            'manage_options',
            'QPlayer2',
            array($this, 'create_admin_page')
        );
    }

    /** @noinspection HtmlUnknownTarget */
    public function create_admin_page()
    {
        echo '<h2>QPlayer2</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::PAGE);
        submit_button();
        echo '</form>';
    }

    public function page_init()
    {
        $this->initOptions();

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array($this, 'sanitize')
        );

        add_settings_section(
            self::SECTION,
            null,
            null,
            self::PAGE
        );

        $this->checkbox(
            'general',
            __('常规', 'QPlayer2'),
            array(
                'cdn' => '使用 jsDelivr CDN 免费加速 js、css 文件',
                'isRotate' => '旋转封面',
                'isShuffle' => '随机播放',
                'isAutoplay' => '自动播放',
                'isPauseOtherWhenPlay' => '在播放时暂停其他媒体',
                'isPauseWhenOtherPlay' => '在其他媒体播放时暂停'
            )
        );

        $this->text(
            'color',
            __('主题颜色', 'QPlayer2'),
            __('默认：<span style="color: #EE1122">#EE1122</span>', 'QPlayer2')
        );

        $this->radio(
            'bitrate',
            __('默认音质', 'QPlayer2'),
            array(
                '128' => __('流畅品质 128K', 'QPlayer2'),
                '192' => __('清晰品质 192K', 'QPlayer2'),
                '320' => __('高品质 320K', 'QPlayer2')
            )
        );

        $this->textarea(
            'list',
            __('歌曲别表', 'QPlayer2'),
            __('
<a href="https://www.json.cn/">JSON 格式</a> 的数组，具体属性请看
<a href="https://github.com/moeshin/QPlayer2#list-item">这里</a><br>
您也可以添加，例如：私人雷达<br>
<code>{"server": "netease", "type": "playlist", "id": "3136952023"}</code><br>
来引入第三方资源，此功能基于 <a href="https://github.com/metowolf/Meting">Meting</a><br>
<code>server</code>：netease、tencent、baidu、xiami、kugou<br>
<code>type</code>：playlist、song、album、artist<br>
（附：<a target="_blank" href="https://github.com/moeshin/netease-music-dynamic-playlist">网易云动态歌单整理</a>）
')
        );

        $this->textarea(
            'cookie',
            __('网易云音乐 Cookie', 'QPlayer2'),
            __('
如果您是网易云音乐的会员或者使用私人雷达，可以将您的 cookie 的 MUSIC_U 填入此处来获取云盘等付费资源，听歌将不会计入下载次数<br>
<strong>如果不知道这是什么意思，忽略即可</strong>
')
        );

        $this->radio(
            'cacheType',
            __('缓存类型', 'QPlayer2'),
            array(
                'none' => __('无', 'QPlayer2'),
                'database' => __('数据库', 'QPlayer2'),
                'memcached' => __('Memcached', 'QPlayer2'),
                'redis' => __('Redis', 'QPlayer2')
            )
        );

        $this->text(
            'cacheHost',
            __('缓存地址', 'QPlayer2'),
            __('若使用数据库缓存，请忽略此项。默认：127.0.0.1', 'QPlayer2')
        );

        $this->text(
            'cachePort',
            __('缓存端口', 'QPlayer2'),
            __('若使用数据库缓存，请忽略此项。默认，Memcached：11211；Redis：6379', 'QPlayer2')
        );

        add_settings_field(
            'cleanCache',
            null,
            function () {
                echo '<a target="_blank" href="' . admin_url('admin-ajax.php?action=QPlayer2') . '&do=flush">
<button type="button" class="button">' . __('清除缓存', 'QPlayer2') . '</button></a>';
            },
            self::PAGE,
            self::SECTION
        );
    }

    private function checked($bool)
    {
        return $bool ? 'checked="checked"' : '';
    }

    private function addSettingsField($id, $title, $callback)
    {
        add_settings_field(
            $id,
            $title,
            $callback,
            self::PAGE,
            self::SECTION);
    }

    private function checkbox($id, $title, $options)
    {
        $that = $this;
        $this->addSettingsField(
            $id,
            $title,
            function () use ($that, $options) {
                foreach ($options as $id => $text) {
                    $checked = $that->checked(isset($that->options[$id]));
                    echo <<<HTML
<p>
    <label for="$id">
        <input id="$id" name="{$that->getName($id)}" type="checkbox" $checked> $text
    </label>
</p>
HTML;
                }
            }
        );
    }

    private function radio($id, $title, $options)
    {
        $that = $this;
        $this->addSettingsField(
            $id,
            $title,
            function () use ($that, $id, $options) {
                $option = $that->options[$id];
                foreach ($options as $value => $text) {
                    $checked = $that->checked($option == $value);
                    $cid = "$id-$value";
                    echo <<<HTML
<p>
    <label for="$cid">
        <input id="$cid" name="{$that->getName($id)}" type="radio" value="$value" $checked> $text
    </label>
</p>
HTML;
                }
            }
        );
    }

    private function text($id, $title, $description)
    {
        $that = $this;
        $this->addSettingsField(
            $id,
            $title,
            function () use ($that, $id, $description) {
                $r = esc_attr($that->options[$id]);
                echo <<<HTML
<input id="$id" name="{$that->getName($id)}" type="text" class="regular-text" value="$r">
<p class="description">$description</p>
HTML;
            }
        );
    }

    private function textarea($id, $title, $description)
    {
        $that = $this;
        $this->addSettingsField(
            $id,
            $title,
            function () use ($that, $id, $description) {
                $r = esc_textarea($that->options[$id]);
                echo <<<HTML
<textarea id="$id" name="{$that->getName($id)}" type="text" class="large-text" style="height: 100px">$r</textarea>
<p class="description">$description</p>
HTML;
            }
        );
    }

    private function getName($key) {
        return self::OPTION_NAME . "[$key]";
    }

    public function sanitize($input)
    {
        $r = array();

        // Normal
        $keys = array(
            'cdn',
            'isRotate',
            'isShuffle',
            'isAutoplay',
            'isPauseOtherWhenPlay',
            'isPauseWhenOtherPlay',
            'bitrate',
            'cacheType'
        );
        foreach ($keys as $key) {
            $in = $input[$key];
            if (isset($in)) {
                $r[$key] = $in;
            }
        }

        // Text
        $keys = array(
            'color',
            'cacheHost',
            'cachePort'
        );
        foreach ($keys as $key) {
            $in = $input[$key];
            if (isset($in)) {
                $r[$key] = sanitize_text_field($in);
            }
        }

        // Textarea
        $keys = array(
            'list',
            'cookie'
        );
        foreach ($keys as $key) {
            $in = $input[$key];
            if (isset($in)) {
                $r[$key] = sanitize_textarea_field($in);
            }
        }

        $options = $this->options;

        // Handle Cache
        $this->requireCache();
        $cacheTypeNow = $input['cacheType'];
        $cacheArgs = array(
            $cacheTypeNow,
            $input['cacheHost'],
            $input['cachePort']
        );
        $cacheTypeLast = $options['cacheType'];
        $cacheBuild = array('QPlayer\Cache\Cache', 'Build');
        $isNotNoneNow = $cacheTypeNow != 'none';
        if ($cacheTypeNow != $cacheTypeLast) {
            if ($isNotNoneNow) {
                $cache = call_user_func_array($cacheBuild, $cacheArgs);
                $cache->install();
                $cache->test();
            }
            if ($cacheTypeLast != 'none') {
                Cache::UninstallWithOptions($options);
            }
        } elseif (
            $isNotNoneNow &&
            $cacheTypeNow != 'database' &&
            self::compareCacheConfig($input, $options)
        ) {
            $cache = call_user_func_array($cacheBuild, $cacheArgs);
            $cache->test();
        }

        return $r;
    }

    private static function compareCacheConfig($now, $last) {
        $keys = array('cacheHost', 'cachePort');
        $length = count($keys);
        for ($i = 0; $i < $length; ++$i) {
            $key = $keys[$i];
            if ($now[$key] != $last->$key) {
                return true;
            }
        }
        return false;
    }

    private function getBool($key)
    {
        return isset($this->options[$key]);
    }

    private function getBoolString($key)
    {
        return isset($this->options[$key]) ? 'true' : 'false';
    }

    private function getString($key)
    {
        return htmlspecialchars_decode($this->options[$key], ENT_QUOTES);
    }

    public function footer()
    {
        $this->initOptions();
        if ($this->getBool('cdn')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $info = get_plugin_data(__FILE__, false, false);
            $prefix = 'https://cdn.jsdelivr.net/gh/moeshin/QPlayer2-WordPress@' . $info['Version'] . '/assets';
        } else {
            $prefix = plugins_url('assets', __FILE__);
        }
        $api = admin_url('admin-ajax.php?action=QPlayer2');
        /**
         * @noinspection BadExpressionStatementJS
         * @noinspection JSUnnecessarySemicolon
         */
        echo <<<HTML
<link rel="stylesheet" href="$prefix/QPlayer.min.css">
<script src="$prefix/QPlayer.min.js"></script>
<script src="$prefix/QPlayer-plugin.js"></script>
<script>
(function() {
    var q = window.QPlayer;
    var plugin = q.plugin;
    var $ = q.$;
    plugin.api = '$api';
    plugin.setList({$this->getString('list')});
    q.isRoate = {$this->getBoolString('isRotate')};
    q.isShuffle = {$this->getBoolString('isShuffle')};
    q.isAutoplay = {$this->getBoolString('isAutoplay')};
    q.isPauseOtherWhenPlay = {$this->getBoolString('isPauseOtherWhenPlay')};
    q.isPauseWhenOtherPlay = {$this->getBoolString('isPauseWhenOtherPlay')};
    $(function () {
        q.setColor('{$this->getString('color')}');
    });
})();
</script>
HTML;
    }

    /**
     * @throws Exception
     */
    public function ajax() {
        require_once 'QPlayer2_Ajax.php';
        new QPlayer2_Ajax($this->options);
        exit;
    }

    private function requireCache() {
        require_once 'libs/cache/Cache.php';
    }
}

new QPlayer2();
