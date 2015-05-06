<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\model;

use codiverum\abstracttree\components\interfaces\TreeNodeInterface;
use yii\base\Model;

/**
 * Description of Node
 *
 * @author jozek
 */
abstract class AbstractNode extends Model implements TreeNodeInterface {

    public abstract function getNodeTableName();

    public abstract function getNodeAncestorClass();
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent() {
        return $this->hasOne(static::className(), ['id' => 'id_parent_' . $this->getNodeTableName()]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren() {
        return $this->hasMany(static::className(), ['id_parent_' . $this->getNodeTableName() => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAncestors() {
        $nodeAncestorClass = $this->getNodeAncestorClass();
        return $this->hasMany(static::className(), ['id' => 'id_' . $this->getNodeTableName()])
                        ->viaTable($nodeAncestorClass::tableName(), ['id_ancestor_' . $this->getNodeTableName(), 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSiblings() {
        $propertyName = 'id_parent_' . $this->getNodeTableName();
        return static::find()->andWhere(['id_parent_' . $this->getNodeTableName() => $this->$propertyName]);
    }

}
