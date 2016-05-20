<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace helloj\db\dbmaker;

/**
 * TableSchema represents the metadata of a database table.
 *
 * @author Jackie Yu <helloj@gmail.com>
 * @since 1.0
 */

class TableSchema extends \yii\db\TableSchema
{
    public $jsoncolsName;

    private function loadDynamicColumnSchema($name) {
        $c = new ColumnSchema();
        $c->name = $name;
        $c->allowNull = true;
        $c->isPrimaryKey = false;
        $c->isForeignKey = false;
        $c->autoIncrement = false;
        $c->dbType = 'VARCHAR';
        $c->dbOrder = -1;
        $c->type = 'string';
        $c->size = $c->precision = 1024;
        $c->scale = -1;
        $c->defaultValue = null;
        $c->unsigned = false;
        $c->enumValues = null;
        $c->phpType = 'string';
        return $c;
    }

    /**
     * Gets the named column metadata, Support DBMaker dynamic columns.
     * This is a convenient method for retrieving a named column even if it does not exist.
     * @param string $name column name
     * @return ColumnSchema metadata of the named column. Null if the named column does not exist.
     */
    public function getColumn($name)
    {
        if (isset($this->columns[$name]))
            return $this->columns[$name];
        if (isset($this->jsoncolsName))
            return $this->loadDynamicColumnSchema($name);
        return isset($this->columns[$name]) ? $this->columns[$name] : null;
    }
}
