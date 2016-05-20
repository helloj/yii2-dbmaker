DBMaker Extension for Yii 2 (yii2-dbmaker)
============================================
This extension adds [DBMaker](http://www.dbmaker.com.tw/) database engine extension for the [Yii framework 2.0](http://www.yiiframework.com).

This branch use the last developer version of Yii2 (dev-master)

Requirements
------------
 * DBMaker Client driver installed
 * PHP module pdo_odbc
 * DBMaker Database Server 5.4 or greater

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
php composer.phar require --prefer-dist "helloj/yii2-dbmaker:*"
```

or add

```json
"helloj/yii2-dbmaker": "*"
```

to the require section of your composer.json.


Configuration
-------------

To use this extension, simply add the following code in your application configuration:

Using DBMaker:

```php
return [
    //....
    'components' => [
        'db' => [
            'class'         => 'helloj\db\dbmaker\Connection',
            'dsn'           => 'odbc:DSN=MYDB;CLILCODE=UTF-8;ERRLCODE=en.UTF-8',
            'username'      => 'username',
            'password'      => 'password',
        ],
    ],
];
```
