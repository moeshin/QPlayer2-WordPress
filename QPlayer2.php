<?php
/*
Plugin Name: QPlayer2
Description: 一款简洁小巧的 HTML5 底部悬浮音乐播放器
Author: MoeShin
Version: 2.0.0
Author URI: https://www.moeshin.com/
*/
class QPlayer2 {

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
    }

    private function initOptions()
    {
        $options = get_option(self::OPTION_NAME, $this->getDefault());
        $this->options = is_array($options) ? $options : array();
    }

    private function getDefault()
    {
        $names = array('cdn', 'jQuery', 'isRotate', 'isShuffle');
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
}]'
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
    }

    public function add_plugin_page() {
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
            __('常规'),
            array(
                'cdn' => '使用 jsDelivr CDN 免费加速 js、css 文件',
                'jQuery' => '加载 jQuery。若冲突，请关闭',
                'isRotate' => '旋转封面',
                'isShuffle' => '随机播放'
            )
        );

        $this->text(
            'color',
            __('主题颜色'),
            __('默认：<span style="color: #EE1122">#EE1122</span>')
        );

        $this->radio(
            'bitrate',
            __('默认音质'),
            array(
                '128' => __('流畅品质 128K'),
                '192' => __('清晰品质 192K'),
                '320' => __('高品质 320K')
            ),
            '320'
        );

        $this->textarea(
            'list',
            __('歌曲别表'),
            __(<<<HTML
JSON 格式的数组，具体属性请看 <a href="https://github.com/moeshin/QPlayer2#list-item">这里</a><br>
您也可以添加，例如：私人雷达<br>
<code>{"server": "netease", "type": "playlist", "id": "3136952023"}</code><br>
来引入第三方资源，此功能基于 <a href="https://github.com/metowolf/Meting">Meting</a><br>
<code>server</code>：netease、tencent、baidu、xiami、kugou<br>
<code>type</code>：playlist、song、album、artist
HTML)
        );

        $this->textarea(
            'cookie',
            __('网易云音乐 Cookie'),
            __(<<<HTML
如果您是网易云音乐的会员或者使用私人雷达，可以将您的 cookie 的 MUSIC_U 填入此处来获取云盘等付费资源，听歌将不会计入下载次数<br>
<strong>如果不知道这是什么意思，忽略即可</strong>
HTML)
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

    private function radio($id, $title, $options, $default)
    {
        $option = $this->options[$id];
        if (!isset($option)) {
            $option = $default;
        }
        $that = $this;
        $this->addSettingsField(
            $id,
            $title,
            function () use ($that, $id, $options, $option) {
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
        $keys = array('cdn', 'jQuery', 'isRotate', 'isShuffle', 'bitrate');
        foreach ($keys as $key) {
            $in = $input[$key];
            if (isset($in)) {
                $r[$key] = $in;
            }
        }
        $keys = array('color');
        foreach ($keys as $key) {
            $in = $input[$key];
            if (isset($in)) {
                $r[$key] = sanitize_text_field($in);
            }
        }
        $keys = array('list', 'cookie');
        foreach ($keys as $key) {
            $in = $input[$key];
            if (isset($in)) {
                $r[$key] = sanitize_textarea_field($in);
            }
        }
        return $r;
    }

    public function footer()
    {
        $this->initOptions();
        echo 'QPlayer2 Hooked!';
    }
}

new QPlayer2();