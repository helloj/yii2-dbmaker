<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace helloj\db\dbmaker;

use PDO;

/**
 * @author Jackie Yu <helloj@gmail.com>
 * @since 1.0
 */
class Connection extends \yii\db\Connection
{
    /**
     * @var array PDO attributes (name => value) that should be set when calling [[open()]]
     * to establish a DB connection. Please refer to the
     * [PHP manual](http://www.php.net/manual/en/function.PDO-setAttribute.php) for
     * details about available attributes.
     */
    public $attributes = [
        PDO::ATTR_CASE => PDO::CASE_LOWER,
    ];
    
    /**
     * @var array mapping between PDO driver names and [[Schema]] classes.
     * The keys of the array are PDO driver names while the values the corresponding
     * schema class name or configuration. Please refer to [[Yii::createObject()]] for
     * details on how to specify a configuration.
     *
     * This property is mainly used by [[getSchema()]] when fetching the database schema information.
     * You normally do not need to set this property unless you want to use your own
     * [[Schema]] class to support DBMS that is not supported by Yii.
     */
    public $schemaMap = [
        'dbmaker' => 'helloj\db\dbmaker\Schema', // DBMaker
        'odbc' => 'helloj\db\dbmaker\Schema', // DBMaker ODBC
    ];
}
