<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\migrations;

use yii\db\Migration;
use yii\db\Schema;

/**
 * Description of BaseMigration
 *
 * @author jozek
 */
abstract class AbstractTreeBaseMigration extends Migration {

    /**
     * @return name of the node table (eg. node or category)
     */
    public abstract function getNodeTableName();

    /**
     * @return node ancestor link table (defaults to getNodeTableName()."_ancestor")
     */
    public function getNodeAncestorTableName() {
        return $this->getNodeTableName() . '_ancestor';
    }

    /**
     * @return array[] array of extra columns in node table
     */
    public abstract function getExtraNodeTableColumns();

    public function up() {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $nodeTableName = $this->getNodeTableName();
        $nodeAncestorTableName = $this->getNodeAncestorTableName();

        $nodeColumns = [
            'id' => Schema::TYPE_PK,
            'id_parent_' . $nodeTableName => Schema::TYPE_INTEGER . ' DEFAULT NULL',
            $nodeTableName.'_level' => Schema::TYPE_INTEGER . ' NOT NULL',
        ];
        $nodeColumns = array_merge($nodeColumns, $this->getExtraNodeTableColumns());

        $this->createTable($nodeTableName, $nodeColumns, $tableOptions);

        $this->createTable($nodeAncestorTableName, [
            'id_' . $nodeTableName => Schema::TYPE_INTEGER . ' NOT NULL',
            'id_ancestor_' . $nodeTableName => Schema::TYPE_INTEGER . ' NOT NULL',
                ], $tableOptions);
        $this->addIndexesAndKeys();
    }

    /**
     * Adds indexes and keys (primary and foreign)
     */
    protected function addIndexesAndKeys() {
        $nodeName = $this->getNodeTableName();
        $nodeKeyName = str_replace("_", "", $nodeName);
        $nodeAncestorName = $this->getNodeAncestorTableName();
        $nodeAncestorKeyName = str_replace("_", "", $nodeAncestorName);
        $this->createIndex('unique_node', $nodeName, ['name', 'id_parent_' . $nodeName], true);
        $this->addForeignKey("fk_{$nodeKeyName}_parent{$nodeKeyName}", $nodeName, 'id_parent_' . $nodeName, $nodeName, 'id', 'SET NULL', 'CASCADE');
        $this->addPrimaryKey('pk_' . $nodeKeyName . 'ancestor', $nodeAncestorName, ['id_' . $nodeName, 'id_ancestor_' . $nodeName]);
        $this->addForeignKey('fk_' . $nodeAncestorKeyName . '_' . $nodeKeyName, $nodeAncestorName, 'id_' . $nodeName, $nodeName, 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_' . $nodeAncestorKeyName . '_ancestor', $nodeAncestorName, 'id_ancestor_' . $nodeName, $nodeName, 'id', 'CASCADE', 'CASCADE');
    }

    public function down() {
        $this->dropTable($this->getNodeAncestorTableName());
        $this->dropTable($this->getNodeTableName());
    }

}
