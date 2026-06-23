<?php

/**
 * @file tests/mocks/OJSMockLoader.php
 *
 * Defines lightweight stand-ins for the OJS/PKP base classes that
 * ReviewerCertificate / ReviewerCertificateDAO depend on, so the plugin's
 * real classes can be loaded and exercised outside a full OJS install.
 *
 * Must be required BEFORE any plugin class is autoloaded.
 *
 * Note on eval(): the strings below are fixed string literals authored
 * in this file (no external/user input is ever interpolated into them).
 * eval() is used only because PHP has no other way to conditionally
 * declare a namespaced class guarded by class_exists() — this mirrors
 * the same pattern used by the acceptanceLetter plugin's test harness
 * (tests/mocks/OJSMockLoader.php) and only runs under PHPUnit.
 */

class OJSMockLoader
{
    private static $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::defineCore();
        self::defineDataObject();
        self::defineDao();
        self::defineDaoResultFactory();

        self::$initialized = true;
    }

    private static function defineCore(): void
    {
        if (!class_exists('PKP\core\Core', false)) {
            eval('
                namespace PKP\core;
                class Core {
                    public static function getCurrentDate() {
                        return date("Y-m-d H:i:s");
                    }

                    public static function getBaseDir() {
                        return sys_get_temp_dir();
                    }
                }
            ');
        }
    }

    private static function defineDataObject(): void
    {
        if (!class_exists('PKP\core\DataObject', false)) {
            eval('
                namespace PKP\core;
                class DataObject {
                    protected $_data = [];

                    public function setData($key, $value) {
                        $this->_data[$key] = $value;
                    }

                    public function getData($key) {
                        return $this->_data[$key] ?? null;
                    }

                    public function setAllData($data) {
                        $this->_data = $data;
                    }

                    public function getAllData() {
                        return $this->_data;
                    }
                }
            ');
        }
    }

    private static function defineDao(): void
    {
        if (!class_exists('PKP\db\DAO', false)) {
            eval('
                namespace PKP\db;
                class DAO {
                    public function retrieve($sql, $params = []) {
                        $rows = \DatabaseMock::retrieve($sql, $params);
                        $objects = array_map(function ($row) {
                            return (object) $row;
                        }, $rows);
                        return new \ArrayIterator($objects);
                    }

                    public function update($sql, $params = []) {
                        return \DatabaseMock::update($sql, $params);
                    }

                    protected function _getInsertId($tableName = null, $idField = null) {
                        return \DatabaseMock::$lastInsertId ?? 0;
                    }
                }
            ');
        }
    }

    private static function defineDaoResultFactory(): void
    {
        if (!class_exists('PKP\db\DAOResultFactory', false)) {
            eval('
                namespace PKP\db;
                class DAOResultFactory implements \Iterator {
                    private $items = [];
                    private $position = 0;

                    public function __construct($result, $dao, $method) {
                        foreach ($result as $row) {
                            $this->items[] = $dao->{$method}($row);
                        }
                    }

                    public function toArray() {
                        return $this->items;
                    }

                    #[\ReturnTypeWillChange]
                    public function current() {
                        return $this->items[$this->position] ?? null;
                    }

                    #[\ReturnTypeWillChange]
                    public function key() {
                        return $this->position;
                    }

                    public function next(): void {
                        $this->position++;
                    }

                    public function rewind(): void {
                        $this->position = 0;
                    }

                    public function valid(): bool {
                        return isset($this->items[$this->position]);
                    }
                }
            ');
        }
    }
}
