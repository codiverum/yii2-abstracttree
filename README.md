CodiVerum Yii2 Extension for hierarchy data
========================================
Extension contains abstract classes making it easy to implement tree-structured data in Yii2 Framework.
I've found other solutions implementing tree structured data but I wasn't satisfy by them so 
I created my own way (I haven't found that kind of solution).
It allows for adding nodes, removing nodes, moving nodes (with or without subtree), 
getting ancestors, descendants, siblings etc. 
I hope you'll find it useful and easy.

This is my first open-source work so I'll be glad to hear any suggestions.
I'm sure this little library may be improved.

The basic design puts the data into two tables (names are easily customizable - see Usage):

1. node:
```
id INT PRIMARY KEY auto_increment,
id_parent_node INT DEFAULT NULL,
node_level INT NOT NULL
```

2. node_ancestor:
```
id_node INT NOT NULL,
id_ancestor_node INT NOT NULL,
```
Proper foreign keys are also set.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist codiverum/yii2-abstract-tree "*"
```

or add

```
"codiverum/yii2-abstract-tree": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Using this extension you can easily make:
- customized migration 
- ActiveRecord classes 

### Migration ###
To make migration, you should create new migration:

```
yii migrate/create migration_name
```

Your migration should extend `codiverum\abstracttree\migrations\AbstractTreeBaseMigration` class, for example:

~~~php
use codiverum\abstracttree\migrations\AbstractTreeBaseMigration;
use yii\db\Schema;

class m150505_194334_category_tree extends AbstractTreeBaseMigration {

    public function up() {
        parent::up();
        $this->addForeignKey('fk_category_usercreated', 'category', 'id_user_created', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function getExtraNodeTableColumns() {
        return [
            'id_user_created' => Schema::TYPE_INTEGER,
            'name' => Schema::TYPE_STRING . '(128) NOT NULL',
            'description' => Schema::TYPE_TEXT,
            'created_at' => Schema::TYPE_INTEGER,
            'updated_at' => Schema::TYPE_INTEGER
        ];
    }

    public function getNodeTableName() {
        return 'category';
    }
}
~~~
