<?php


namespace QPlayer\Cache;


class Database extends Cache
{
    protected $db;
    protected $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $this->db->prefix . 'QPlayer2';
    }

    public function set($key, $data, $expire = 86400)
    {
        $this->db->insert($this->table, array(
            'key' => md5($key),
            'data' => $data,
            'time' => time() + $expire
        ));
    }

    /** @noinspection SqlResolve */
    public function get($key)
    {
        // Recycle
        $this->db->query("DELETE FROM `$this->table` WHERE `time` <= " . time());

        $key = md5($key);
        $row = $this->db->get_row("SELECT `data` FROM `$this->table` WHERE `key` = '$key'");
        @$data = $row->data;
        return $data;
    }

    public function install()
    {
        $sql = <<<SQL
CREATE TABLE `$this->table` (
    `key` CHAR(32) NOT NULL,
    `data` MEDIUMTEXT NOT NULL,
    `time` INT(10) NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
SQL;
        $this->db->query($sql);
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `$this->table`;");
    }

    public function flush()
    {
        $this->db->query("TRUNCATE TABLE `$this->table`");
    }
}