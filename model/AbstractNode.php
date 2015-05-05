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

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent() {
        return $this->hasOne(Category::className(), ['id' => 'id_parent_' . $this->getNodeTableName()]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren() {
        return $this->hasMany(Category::className(), ['id_parent_' . $this->getNodeTableName() => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAncestors() {
        return $this->hasMany(Category::className(), ['id' => 'id_' . $this->getNodeTableName()])
                        ->viaTable(CategoryAncestor::tableName(), ['id_ancestor_' . $this->getNodeTableName(), 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSiblings() {
        $propertyName = 'id_parent_' . $this->getNodeTableName();
        return Category::find()->andWhere(['id_parent_' . $this->getNodeTableName() => $this->$propertyName]);
    }

}
