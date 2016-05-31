<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace helloj\db\dbmaker;

use PDO;
use yii\db\Expression;

/**
 * @author Jackie Yu <helloj@gmail.com>
 * @since 1.0
 */

class Schema extends \yii\db\Schema
{
    public $typeMap = [
        'BIGINT'     => self::TYPE_BIGINT,
        'BIGSERIAL'  => self::TYPE_BIGINT,
        'BINARY'     => self::TYPE_BINARY,
        'BLOB'       => self::TYPE_BINARY,
        'CHAR'       => self::TYPE_CHAR,
        'CLOB'       => self::TYPE_TEXT,
        'DATE'       => self::TYPE_DATE,
        'DECIMAL'    => self::TYPE_DECIMAL,
        'DOUBLE'     => self::TYPE_DOUBLE,
        'FILE'       => self::TYPE_BINARY,
        'FLOAT'      => self::TYPE_FLOAT,
        'INT'        => self::TYPE_INTEGER,
        'INTEGER'    => self::TYPE_INTEGER,
        'JSONCOLS'   => self::TYPE_TEXT,
        'LONG VARBINARY' => self::TYPE_BINARY,
        'LONG VARCHAR'   => self::TYPE_TEXT,
        'NCHAR'      => self::TYPE_CHAR,
        'NVARCHAR'   => self::TYPE_STRING,
        'REAL'       => self::TYPE_FLOAT,
        'SERIAL'     => self::TYPE_INTEGER,
        'SMALLINT'   => self::TYPE_SMALLINT,
        'TIME'       => self::TYPE_TIME,
        'TIMESTAMP'  => self::TYPE_TIMESTAMP,
        'VARCHAR'    => self::TYPE_STRING,
    ];


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->defaultSchema === null) {
            $this->defaultSchema = strtoupper($this->db->username);
        }
    }

    /**
     * @inheritdoc
     */
    public function quoteSimpleTableName($name)
    {
        return strpos($name, '"') !== false ? $name : '"' . $name . '"';
    }

    /**
     * @inheritdoc
     */
    public function quoteSimpleColumnName($name)
    {
        return strpos($name, '"') !== false || $name === '*' ? $name : '"' . $name . '"';
    }

    /**
     * Creates a query builder for the MySQL database.
     * @return QueryBuilder query builder instance
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }

    /**
     * @inheritdoc
     */
    protected function loadTableSchema($name)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        if ($this->findColumns($table)) {
            $this->findPrimaryKey($table);
            $this->findConstraints($table);
            return $table;
        } else {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getTableSchema($name, $refresh = false)
    {
        // Internal table name is uppercase and
        // to ensure the consistency of the key cache
        return parent::getTableSchema(strtoupper($name), $refresh);
    }

    /**
     * @inheritdoc
     */
    protected function resolveTableNames($table, $name)
    {
        $parts = explode('.', str_replace('"', '', $name));
        if (isset($parts[1])) {
            $table->schemaName = $parts[0];
            $table->name = $parts[1];
            $table->fullName = $table->schemaName . '.' . $table->name;
        } else {
            $table->schemaName = $this->defaultSchema;
            $table->name = $name;
            $table->fullName = $table->name;
        }
    }

    /**
     * Determines the PDO type for the given PHP data value.
     * @param mixed $data the data whose PDO type is to be determined
     * @return integer the PDO type
     * @see http://www.php.net/manual/en/pdo.constants.php
     */
    public function getPdoType($data)
    {
        static $typeMap = [
            // php type => PDO type
            'boolean'  => \PDO::PARAM_INT, // PARAM_BOOL is not supported by DBMaker PDO
            'integer'  => \PDO::PARAM_INT,
            'string'   => \PDO::PARAM_STR,
            'resource' => \PDO::PARAM_LOB,
            'NULL'     => \PDO::PARAM_NULL,
        ];
        $type = gettype($data);

        return isset($typeMap[$type]) ? $typeMap[$type] : \PDO::PARAM_STR;
    }

     /**
     * Collects the table column metadata.
     * @param TableSchema $table the table schema
     * @return boolean whether the table exists
     */
    protected function findColumns($table)
    {
        $sql = <<<SQL
            SELECT LOWER(RTRIM(COLUMN_NAME)) COLUMN_NAME,
                   COLUMN_ORDER,
                   NULLABLE,
                   RTRIM(TYPE_NAME) TYPE_NAME,
                   LENGTH,
                   PRECISION,
                   SCALE,
                   RADIX,
                   ASCII_DEF
            FROM SYSTEM.SYSCOLUMN
            WHERE TABLE_NAME = UPPER(:tableName)
              AND TABLE_OWNER = UPPER(:schemaName)
              AND RESERVE6 > 0
            ORDER BY COLUMN_ORDER
SQL;

        try {
            $columns = $this->db->createCommand($sql, [
                ':tableName' => $table->name,
                ':schemaName' => $table->schemaName,
            ])->queryAll();
        } catch(\Exception $e) {
            return false;
        }

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->loadColumnSchema($column);
            $table->columns[$c->name] = $c;
            if ($c->dbType == 'JSONCOLS')
                $table->jsoncolsName = $c->name;
        }
        return true;
    }

    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $column column information
     * @return ColumnSchema the column schema object
     */
    protected function loadColumnSchema($column)
    {
        $c = new ColumnSchema();
        $c->name = $column['column_name'];
        $c->allowNull = $column['nullable']==1;
        $c->isPrimaryKey = false;
        $c->isForeignKey = false;
        $c->dbType = $column['type_name'];
        $c->dbOrder = (int)$column['column_order'];
        $c->autoIncrement = ($c->dbType == 'SERIAL' || $c->dbType == 'BIGSERIAL');

        $c->type = $this->typeMap[$column['type_name']];
        $c->size = $column['length'];
        $c->precision= $column['precision'];
        $c->scale = $column['scale'];
        if ($column['radix'] > 0)  // we have a numeric datatype
            $c->size = $c->precision = $column['precision'];
         elseif ($c->dbType=='LONG VARBINARY' || $c->dbType=='LONG VARCHAR')
            $c->size = $c->precision = null;
        elseif ($c->dbType=='TIMESTAMP')
            $c->size = $c->precision = 27;
        elseif ($c->dbType=='DATE')
            $c->size = $c->precision = 11;
        elseif ($c->dbType=='TIME')
            $c->size = $c->precision = 15;

        if (ord($column['ascii_def'][0]) === 0)
            $c->defaultValue = null;
        else {
            $defVal = strtoupper(trim($column['ascii_def']));
            if ($c->type === 'timestamp' && $defVal === 'CURRENT_TIMESTAMP')
                $c->defaultValue = new Expression('CURRENT_TIMESTAMP');
            else {
                if (!strcasecmp($defVal,'NULL'))
                    $defVal = null;
                elseif (strlen($defVal) > 2 && $defVal[0] === "'" && substr($defVal, -1) === "'")
                    $defVal = substr($defVal, 1, -1);
                $c->defaultValue = $c->phpTypecast($defVal);
            }
        }

        $c->unsigned = false;
        $c->enumValues = null;
        $c->phpType = $this->getColumnPhpType($c);

        return $c;
    }

    /**
     * Collects primary key information.
     * @param TableSchema $table the table schema
     */
    protected function findPrimaryKey($table) {
        $sql = <<<SQL
            SELECT RESERVE3,
                   NUM_COL
            FROM SYSTEM.SYSINDEX
            WHERE UNIQUE = 3
              AND TABLE_NAME = UPPER(:tableName)
              AND TABLE_OWNER = UPPER(:schemaName)
SQL;

        $command = $this->db->createCommand($sql, [
            ':tableName' => $table->name,
            ':schemaName' => $table->schemaName,
        ]);
        foreach ($command->queryAll() as $index) {
            for ($i = 0; $i < $index['num_col']; $i++) {
                $buf = $index['reserve3'][$i*4+2] . $index['reserve3'][$i*4+3] .
                    $index['reserve3'][$i*4] . $index['reserve3'][$i*4+1];
                $order = hexdec($buf);
                foreach ($table->columns as $column) { /* @var ColumnSchema $column */
                    if ($column->dbOrder === $order) {
                        $column->isPrimaryKey = true;
                        $table->primaryKey[] = $column->name;
                        if ($column->autoIncrement) {
                            $table->sequenceName = $column->name;
                        }
                        break;
                    }
                }
            }
        }
    }

    /**
     * Finds constraints and fills them into TableSchema object passed
     * @param TableSchema $table
     */
    protected function findConstraints($table)
    {
        $sql = <<<SQL
            SELECT FK_NAME,
                   FK_COL_ORDER,
                   RTRIM(PK_TBL_NAME) PK_TBL_NAME,
                   RTRIM(PK_TBL_OWNER) PK_TBL_OWNER,
                   PK_COL_ORDER
            FROM SYSTEM.SYSFOREIGNKEY
            WHERE FK_TBL_NAME = UPPER(:tableName)
              AND FK_TBL_OWNER = UPPER(:schemaName)
SQL;

        $command = $this->db->createCommand($sql, [
            ':tableName' => $table->name,
            ':schemaName' => $table->schemaName,
        ]);

        $constraints = [];
        foreach ($command->queryAll() as $index) {
            $pk_tbl_name = $index['pk_tbl_name'];
            $pk_tbl_owner = $index['pk_tbl_owner'];
            $name = $index['fk_name'];
            if (!isset($constraints[$name])) {
                $constraints[$name] = [
                    'tableName' => $index['pk_tbl_name'],
                    'columns' => [],
                ];
            }

            for ($i = 0; $i < 64; $i++) {
                $buf = $index['fk_col_order'][$i * 4 + 3] . $index['fk_col_order'][$i * 4 + 2] .
                    $index['fk_col_order'][$i * 4] . $index['fk_col_order'][$i * 4 + 1];
                $fkorder = hexdec($buf);
                if ($fkorder == 0) {
                    break;  // no more key sequence
                }
                $buf = $index['pk_col_order'][$i * 4 + 3] . $index['pk_col_order'][$i * 4 + 2] .
                    $index['pk_col_order'][$i * 4] . $index['pk_col_order'][$i * 4 + 1];
                $pkorder = hexdec($buf);

                foreach ($table->columns as $column) { /* @var ColumnSchema $column */
                    if ($column->dbOrder === $fkorder) {
                        $column->isForeignKey = true;
                        $constraints[$name]['columns'][$column->name] = $pkorder;
                        break;
                    }
                }
            }

            if ($table->schemaName == $pk_tbl_owner && $table->name == $pk_tbl_name) {
                $pktbl = $table;  // avoid infinite recursion
            } else {
                $pktbl = $this->getTableSchema($pk_tbl_owner. '.' .$pk_tbl_name);
            }

            foreach ($constraints[$name]['columns'] as $key => $val) {
                foreach ($pktbl->columns as $column) {
                    if ($column->dbOrder === $val) {
                        $constraints[$name]['columns'][$key] = $column->name;
                    }
                }
            }
        }
        foreach ($constraints as $constraint) {
            $table->foreignKeys[] = array_merge([$constraint['tableName']], $constraint['columns']);
        }
    }

    /**
     * @inheritdoc
     */
    protected function findSchemaNames()
    {
        $sql = <<<SQL
            SELECT RTRIM(SCHEMA_NAME)
              FROM SYSTEM.SYSSCHEMA
             WHERE SCHEMA_OWNER <> 'SYSTEM'
SQL;
        return $this->db->createCommand($sql)->queryColumn();
    }

    /**
     * @inheritdoc
     */
    protected function findTableNames($schema = '')
    {
        if ($schema === '') {
            $sql = <<<SQL
            SELECT RTRIM(TABLE_NAME) TABLE_NAME
            FROM SYSTABLE
            ORDER BY TABLE_NAME
SQL;
            $command = $this->db->createCommand($sql);
        } else {
            $sql = <<<SQL
            SELECT RTRIM(TABLE_NAME) TABLE_NAME
            FROM SYSTABLE
            WHERE TABLE_OWNER = UPPER(:schemaName)
            ORDER BY TABLE_NAME
SQL;
            $command = $this->db->createCommand($sql, [':schemaName' => $schema]);
        }
        return $command->queryColumn();
    }

    /**
     * @inheritdoc
     */
    public function findUniqueIndexes($table)
    {
        $sql = <<<SQL
            SELECT RTRIM(INDEX_NAME) INDEX_NAME,
                   RESERVE3,
                   NUM_COL
            FROM SYSTEM.SYSINDEX
            WHERE UNIQUE = 1
              AND TABLE_NAME = UPPER(:tableName)
              AND TABLE_OWNER = UPPER(:schemaName)
SQL;
        $command = $this->db->createCommand($sql, [
            ':tableName' => $table->name,
            ':schemaName' => $table->schemaName,
        ]);
        $result = [];
        foreach ($command->queryAll() as $index) {
            for ($i = 0; $i < $index['num_col']; $i++) {
                $buf = $index['reserve3'][$i*4+2] . $index['reserve3'][$i*4+3] .
                    $index['reserve3'][$i*4] . $index['reserve3'][$i*4+1];
                $order = hexdec($buf);
                foreach ($table->columns as $column) { /* @var ColumnSchema $column */
                    if ($column->dbOrder === $order) {
                        $result[$index['index_name']][] = $column->name;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getLastInsertID($sequenceName = '')
    {
        return $this->db->createCommand("SELECT LAST_SERIAL FROM SYSCONINFO")->queryScalar();
    }

    /**
     * @inheritdoc
     */
    public function releaseSavepoint($name)
    {
        $this->db->createCommand("REMOVE SAVEPOINT $name")->execute();
    }
}
