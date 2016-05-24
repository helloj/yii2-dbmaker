<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace helloj\db\dbmaker;

use yii\base\InvalidParamException;
use yii\base\NotSupportedException;
use yii\db\Expression;

/**
 * QueryBuilder is the query builder for DB2 databases.
 *
 * @author Jackie Yu <helloj@gmail.com>
 */
class QueryBuilder extends \yii\db\QueryBuilder
{
    public $typeMap = [
        Schema::TYPE_PK => 'serial PRIMARY KEY',
        Schema::TYPE_UPK => 'serial PRIMARY KEY',
        Schema::TYPE_BIGPK => 'bigserial PRIMARY KEY',
        Schema::TYPE_UBIGPK => 'bigserial PRIMARY KEY',
        Schema::TYPE_CHAR => 'char(1)',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_TEXT => 'clob',
        Schema::TYPE_SMALLINT => 'smallint',
        Schema::TYPE_INTEGER => 'integer',
        Schema::TYPE_BIGINT => 'bigint',
        Schema::TYPE_FLOAT => 'float',
        Schema::TYPE_DOUBLE => 'double',
        Schema::TYPE_DECIMAL => 'decimal(10,0)',
        Schema::TYPE_DATETIME => 'timestamp',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
        Schema::TYPE_BINARY => 'blob',
        Schema::TYPE_BOOLEAN => 'smallint',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];

    /**
     * @inheritdoc
     */
    public function insert($table, $columns, &$params)
    {
        $schema = $this->db->getSchema();
        if (($tableSchema = $schema->getTableSchema($table)) !== null) {
            $columnSchemas = $tableSchema->columns;
        } else {
            $columnSchemas = [];
        }
        $names = [];
        $placeholders = [];
        foreach ($columns as $name => $value) {
            $names[] = $schema->quoteColumnName($name);
            if ($value instanceof Expression) {
                $placeholders[] = $value->expression;
                foreach ($value->params as $n => $v) {
                    $params[$n] = $v;
                }
            } else {
                $phName = self::PARAM_PREFIX . count($params);
                $placeholders[] = $phName;
                $params[$phName] = !is_array($value) && isset($columnSchemas[$name]) ? $columnSchemas[$name]->dbTypecast($value) : $value;
            }
        }

        if (empty($placeholders)) {
            $placeholders = array_fill(0, count($columnSchemas), 'DEFAULT');
        }

        return 'INSERT INTO ' . $schema->quoteTableName($table)
        . (!empty($names) ? ' (' . implode(', ', $names) . ')' : '')
        . ' VALUES (' . implode(', ', $placeholders) . ')';
    }

    /**
     * @inheritdoc
     */
    public function batchInsert($table, $columns, $rows) {
        throw new NotSupportedException(__METHOD__ . ' is not supported by DBMaker.');
    }

    /**
     * @inheritdoc
     */
    public function renameTable($oldName, $newName)
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($oldName) . ' RENAME TO ' . $this->db->quoteTableName($newName);
    }

    /**
     * @inheritdoc
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($columns as $i => $col) {
            $columns[$i] = $this->db->quoteColumnName($col);
        }

        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . '  PRIMARY KEY ('
            . implode(', ', $columns). ' )';
    }

    /**
     * @inheritdoc
     */
    public function dropPrimaryKey($name, $table)
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' DROP PRIMARY KEY ';
    }

    /**
     * @inheritdoc
     */
    public function truncateTable($table)
    {
        return 'DELETE FROM ' . $this->db->quoteTableName($table);
    }

    /**
     * @inheritdoc
     */
    public function renameColumn($table, $oldName, $newName)
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table)
        . ' MODIFY ' . $this->db->quoteColumnName($oldName)
        . ' NAME TO ' . $this->db->quoteColumnName($newName);
    }

    /**
     * @inheritdoc
     */
    public function alterColumn($table, $column, $type)
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' MODIFY ( '
        . $this->db->quoteColumnName($column) . ' TO '
        . $this->db->quoteColumnName($column) . ' '
        . $this->getColumnType($type)         . ')';
    }

    /**
     * @inheritdoc
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $sql = 'ALTER TABLE ' . $this->db->quoteTableName($table)
            . ' FOREIGN KEY ' . $this->db->quoteColumnName($name)
            . ' (' . $this->buildColumns($columns) . ')'
            . ' REFERENCES ' . $this->db->quoteTableName($refTable)
            . ' (' . $this->buildColumns($refColumns) . ')';
        if ($delete !== null) {
            if (!strcasecmp($delete,'RESTRICT'))
                $delete = 'NO ACTION';
            $sql .= ' ON DELETE ' . $delete;
        }
        if ($update !== null) {
            if (!strcasecmp($update,'RESTRICT'))
                $update = 'NO ACTION';
            $sql .= ' ON UPDATE ' . $update;
        }

        return $sql;
    }

    /**
     * @inheritdoc
     */
    public function dropForeignKey($name, $table)
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table)
        . ' DROP FOREIGN KEY ' . $this->db->quoteColumnName($name);
    }

    /**
     * @inheritdoc
     */
    public function dropIndex($name, $table)
    {
        return 'DROP INDEX ' . $this->db->quoteTableName($name) . ' FROM ' . $this->db->quoteTableName($table);
    }

    /**
     * @inheritdoc
     */
    public function resetSequence($tableName, $value = null)
    {
        $table = $this->db->getTableSchema($tableName);

        if ($table !== null && isset($table->columns[$table->sequenceName])) {
            $tableName = $this->db->quoteTableName($tableName);
            $sequence = $this->db->quoteColumnName($table->sequenceName);
            if ($value === null) {
                $sql = "SELECT MAX($sequence) FROM $tableName";
                $value = $this->db->createCommand($sql)->queryScalar() + 1;
            } else {
                $value = (int) $value;
            }
            return "ALTER TABLE $tableName SET SERIAL $value";
        } elseif ($table === null) {
            throw new InvalidParamException("Table not found: $tableName");
        } else {
            throw new InvalidParamException("There is no sequence associated with table '$tableName'.");
        }
    }

    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function addCommentOnColumn($table, $column, $comment)
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by DBMaker.');
    }

    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function addCommentOnTable($table, $comment)
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by DBMaker.');
    }

    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function dropCommentFromColumn($table, $column)
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by DBMaker.');
    }

    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function dropCommentFromTable($table)
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by DBMaker.');
    }

    /**
     * @inheritdoc
     */
    public function buildLimit($limit, $offset)
    {
        $sql = '';
        if ($this->hasLimit($limit)) {
            $sql = 'LIMIT ' . $limit;
            if ($this->hasOffset($offset)) {
                $sql .= ' OFFSET ' . $offset;
            }
        } elseif ($this->hasOffset($offset)) {
            // limit is not optional in DBMaker
            $sql = "LIMIT 9223372036854775807 OFFSET $offset"; // 2^63-1
        }

        return $sql;
    }

    /**
     * @inheritdoc
     */
    public function selectExists($rawSql)
    {
        return 'SELECT CASE WHEN CONNECTION_ID IS NOT NULL THEN 1 ELSE 0 END FROM SYSCONINFO WHERE EXISTS (' . $rawSql . ')';
    }
}
