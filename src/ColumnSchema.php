<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace helloj\db\dbmaker;

/**
 * ColumnSchema class describes the metadata of a column in a database table.
 *
 * @author Jackie Yu <helloj@gmail.com>
 * @since 1.0
 */

class ColumnSchema extends \yii\db\ColumnSchema
{
    public $dbOrder;
    public $isForeignKey;
}
