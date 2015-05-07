<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\model;

use codiverum\abstracttree\components\interfaces\TreeNodeInterface;
use PDO;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Description of Node
 *
 * @author jozek
 */
abstract class AbstractNode extends ActiveRecord implements TreeNodeInterface {

    public $moveWithSubtree = true;
    public $deleteWithSubtree = true;

    public function save($runValidation = true, $attributeNames = null) {
        $transaction = $this->getDb()->beginTransaction();
        try {
            $result = false;
            if ($this->isNewRecord && parent::save() && $this->setAncestors()) {
                $result = true;
            } else
            if ($this->getDirtyParentId() > 0 && $this->$parentIdAttribute != $this->getDirtyParentId() && $this->changeParent() && parent::save()) {
                $result = true;
            }

            if ($result === true) {
                $transaction->commit();
                return true;
            } else {
                $transaction->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }

    public function delete() {
        if ($this->getChildren()->count() == 0)
            return parent::delete();

        $transaction = $this->getDb()->beginTransaction();
        try {
            if ($this->deleteWithSubtree)
                $this->removeDescendants();
            else {
                $idParentAttribute = $this->getIdParentAttribute();
                $this->changeMyChildrensParentToGrandparent($this->$idParentAttribute);
            }
            if (parent::delete()) {
                $transaction->commit();
                return true;
            } else {
                $transaction->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * @return node ancestor link table (defaults to getNodeTableName()."_ancestor")
     */
    public function getNodeAncestorTableName() {
        return $this->tableName() . '_ancestor';
    }

    /**
     * @return string full class name with namespace
     */
    public function getNodeAncestorClass() {
        $words = explode("_", $this->getNodeAncestorTableName());
        if (!empty($words)) {
            foreach ($words as &$word) {
                $word = ucfirst(strtolower($word));
            }
        }
        return implode("", $words);
    }

    /**
     * Returns id parent attribute name (defaults to "id_parent_".getNodeTableName())
     * @return string 
     */
    public function getIdParentAttribute() {
        return "id_parent_" . $this->tableName();
    }

    /**
     * @return ActiveQuery
     */
    public function getParent() {
        return $this->hasOne(static::className(), ['id' => 'id_parent_' . $this->tableName()]);
    }

    /**
     * @return ActiveQuery
     */
    public function getChildren() {
        return $this->hasMany(static::className(), ['id_parent_' . $this->tableName() => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getAncestors() {
        $nodeAncestorClass = $this->getNodeAncestorClass();
        return $this->hasMany(static::className(), ['id' => 'id_ancestor_' . $this->tableName()])
                        ->viaTable($nodeAncestorClass::tableName(), ['id_' . $this->tableName(), 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getSiblings() {
        $propertyName = 'id_parent_' . $this->tableName();
        return static::find()->andWhere(['id_parent_' . $this->tableName() => $this->$propertyName]);
    }

    public function getCategoryLevel() {
        $sql = "SELECT COUNT(" . $this->getLinkIdAncestorAttribute() . ") "
                . " WHERE " . $this->getLinkIdCurrentAttribute() . " = :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->queryScalar();
    }

    public function deleteWithSubtree($turnOn) {
        $this->deleteWithSubtree = $on;
        return $this;
    }

    public function moveWithSubtree($turnOn) {
        $this->moveWithSubtree = $on;
        return $this;
    }

    protected function changeParent() {
        if ($this->moveWithSubtree) {
            return $this->removeMyAncestorsFromMyDescendants() && $this->removeAncestors() && $this->setAncestors($this->getDirtyParentId()) && $this->setMyAncestorsToMyDescendants();
        } else {
            return $this->changeMyChildrensParentToMyParent($this->getDirtyParentId()) && $this->removeMeAsMyDescentantsAncestor();
        }
    }

    /**
     * 
     * @return boolean whether ancestor linked correctly
     */
    protected function setAncestors() {
        if ($this->{$this->getIdParentAttribute()} > 0) {
            $ancestorTblName = $this->getNodeAncestorTableName();
            $id_curr = $this->getLinkIdCurrentAttribute();
            $id_ancestor = $this->getLinkIdAncestorAttribute();
            $sql = "INSERT INTO " . $this->getNodeAncestorTableName()
                    . " ($id_curr, $id_ancestor) "
                    . " (SELECT :id, $id_ancestor FROM $ancestorTblName "
                    . " WHERE $id_curr = " . $this->{$this->getIdParentAttribute()};
            return $this->getDb()
                            ->createCommand($sql)
                            ->bindValue(":id", $this->id, PDO::PARAM_INT)
                            ->execute();
        }

        return true;
    }

    protected function removeAncestors() {
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdCurrentAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    protected function removeDescendants() {
        $sql = "DELETE n FROM " . $this->tableName()
                . " n JOIN " . $this->getNodeAncestorTableName() . " na "
                . " ON na." . $this->getLinkIdCurrentAttribute() . " = n.id "
                . " WHERE na." . $this->getLinkIdAncestorAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    protected function removeMyAncestorsFromMyDescendants() {
        $ancestorIds = implode(",", $this->getAncestorsIds());
        $descentantsIds = implode(", ", $this->getDescendantsIds());
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() . " IN ($ancestorIds)"
                . " AND " . $this->getLinkIdCurrentAttribute() . " IN ($descentantsIds)";
        return $this->getDb()->createCommand($sql)->execute();
    }

    protected function setMyAncestorsToMyDescendants() {
        $id_node = $this->getLinkIdCurrentAttribute();
        $id_anc_node = $this->getLinkIdAncestorAttribute();
        $anc_tbl = $this->getNodeAncestorTableName();
        $sql = "INSERT INTO $anc_tbl($id_node, $id_anc_node) "
                . "(SELECT mydesc.$id_node, myanc.$id_anc_node FROM "
                . "(SELECT $id_node, $id_anc_node FROM $anc_tbl WHERE $id_node = :id) myanc "
                . "JOIN "
                . "(SELECT $id_node, $id_anc_node FROM $anc_tbl WHERE $id_anc_node = :id) mydesc "
                . "ON (myanc.$id_node = mydesc.$id_node))";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    protected function changeMyChildrensParentToGrandparent($idGrandparent) {
        $sql = "UPDATE " . $this->tableName() . " SET " . $this->getIdParentAttribute() . " = :id_parent WHERE " . $this->getIdParentAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->bindValue(":id_parent_node", $idGrandparent, PDO::PARAM_INT)
                        ->execute();
    }

    protected function removeMeAsMyDescentantsAncestor() {
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    protected function getLinkIdCurrentAttribute() {
        return "id_" . $this->tableName();
    }

    protected function getLinkIdAncestorAttribute() {
        return "id_ancestor_" . $this->tableName();
    }

    protected function getAncestorsIds() {
        $sql = "SELECT " . $this->getLinkIdAncestorAttribute()
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdCurrentAttribute() = " :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->queryAll();
    }

    protected function getDescendantsIds() {
        $sql = "SELECT " . $this->getLinkIdCurrentAttribute()
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() = " :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->queryAll();
    }

    protected function getDirtyParentId() {
        $idParentAttribute = $this->getIdParentAttribute();
        if (isset($this->dirtyAttributes) && isset($this->dirtyAttributes[$idParentAttribute]))
            return $this->dirtyAttributes[$idParentAttribute];
        else
            return false;
    }

}
