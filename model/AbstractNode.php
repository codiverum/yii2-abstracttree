<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\model;

use codiverum\abstracttree\components\interfaces\TreeNodeInterface;
use Exception as Exception2;
use PDO;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * Description of Node
 *
 * @author jozek
 */
abstract class AbstractNode extends ActiveRecord implements TreeNodeInterface {

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * Saves the current model
     * @param boolean $runValidation whether to perform validation before saving the record.
     * If the validation fails, the record will not be saved to database.
     * @param array $attributeNames list of attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @param boolean $withSubtree whether subtree sticks to model (e.g. if model parent is changes - should subtree follow model or stay with model's parent)
     * @return boolean whether the saving succeeds
     */
    public function save($runValidation = true, $attributeNames = null, $withSubtree = true) {
        $transaction = $this->getDb()->beginTransaction();
        try {
            $result = false;
            $this->isNewRecord = true;
            if ($this->isNewRecord && parent::save($runValidation, $attributeNames) && $this->setAncestorsLinks()) {
                $result = true;
            } else
            if ($this->getDirtyParentId() > 0 && $this->{$this->getIdParentAttribute()} != $this->getDirtyParentId() && $this->prepareParentChange($withSubtree) && parent::save($runValidation, $attributeNames)) {
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

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeDelete()]]. If the method returns false, it will skip the
     *    rest of the steps;
     * 2. delete the record from the database;
     * 3. call [[afterDelete()]].
     *
     * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
     * will be raised by the corresponding methods.
     *
     * @param boolean $withSubtree whether subtree should be deleted with model or simply attach to model's parent
     * @return integer|false the number of rows deleted, or false if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being deleted is outdated.
     * @throws Exception2 in case delete failed.
     */
    public function delete($withSubtree = false) {
        if ($this->getChildren()->count() == 0)
            return parent::delete();

        $transaction = $this->getDb()->beginTransaction();
        try {
            if ($withSubtree)
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
        return $this->hasMany(static::className(), ['id' => $this->getLinkIdAncestorAttribute()])
                        ->viaTable($this->getNodeAncestorTableName(), [$this->getLinkIdCurrentAttribute(), 'id']);
    }

    /**
     * @param integer $distance
     * @return $this
     */
    public function getAncestorByDistance($distance) {
        $ancestorIds = $this->getAncestorsIds();
        $linkIdCurrent = $this->getLinkIdCurrentAttribute();
        $levelWanted = count($ancestorIds) - $distance;
        if ($levelWanted <= 0)
            return null;
        $sql = "SELECT $linkIdCurrent"
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE $linkIdCurrent IN (" . implode(",", $ancestorIds) . ") "
                . " GROUP BY $linkIdCurrent "
                . " HAVING COUNT(" . $this->getLinkIdAncestorAttribute() . ") = :levelWanted";
        $id = $this->getDb()->createCommand($sql)->bindValue(':levelWanted', $levelWanted, PDO::PARAM_INT)->queryScalar();
        return static::findOne($id);
    }

    /**
     * @param integer $distance
     * @return $this
     */
    public function getDescendantByDistance($distance) {
        $ancestorIds = $this->getDescendantsIds();
        $linkIdCurrent = $this->getLinkIdCurrentAttribute();
        $levelWanted = count($ancestorIds) + $level;
        if ($levelWanted <= 0)
            return null;
        $sql = "SELECT $linkIdCurrent"
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE $linkIdCurrent IN (" . implode(",", $ancestorIds) . ") "
                . " GROUP BY $linkIdCurrent "
                . " HAVING COUNT(" . $this->getLinkIdAncestorAttribute() . ") = :levelWanted";
        $id = $this->getDb()->createCommand($sql)->bindValue(':levelWanted', $levelWanted, PDO::PARAM_INT)->queryScalar();
        return static::findOne($id);
    }

    /**
     * @return ActiveQuery
     */
    public function getDescendants() {
        return $this->hasMany(static::className(), ['id' => $this->getLinkIdCurrentAttribute()])
                        ->viaTable($this->getNodeAncestorTableName(), [$this->getLinkIdCurrentAttribute(), 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getSiblings() {
        $propertyName = 'id_parent_' . $this->tableName();
        return static::find()->andWhere(['id_parent_' . $this->tableName() => $this->$propertyName]);
    }

    public function getLevel() {
        $sql = "SELECT COUNT(" . $this->getLinkIdAncestorAttribute() . ") "
                . " WHERE " . $this->getLinkIdCurrentAttribute() . " = :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->queryScalar();
    }

    public function getAncestorsIds() {
        $sql = "SELECT " . $this->getLinkIdAncestorAttribute()
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdCurrentAttribute() . "= :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->queryAll();
    }

    public function getDescendantsIds() {
        $sql = "SELECT " . $this->getLinkIdCurrentAttribute()
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() . "= :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->queryAll();
    }

    public function removeDescendants() {
        $sql = "DELETE n FROM " . $this->tableName()
                . " n JOIN " . $this->getNodeAncestorTableName() . " na "
                . " ON na." . $this->getLinkIdCurrentAttribute() . " = n.id "
                . " WHERE na." . $this->getLinkIdAncestorAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    protected function prepareParentChange($withSubtree) {
        if ($withSubtree) {
            return $this->removeMyAncestorsFromMyDescendants() && $this->removeAncestorsLinks() && $this->setAncestorsLinks($this->getDirtyParentId()) && $this->setMyAncestorsToMyDescendants();
        } else {
            return $this->changeMyChildrensParentToMyParent($this->getDirtyParentId()) && $this->removeMeAsMyDescendantsAncestor();
        }
    }

    /**
     * 
     * @return boolean whether ancestor linked correctly
     */
    protected function setAncestorsLinks() {
        if ($this->{$this->getIdParentAttribute()} > 0) {
            $ancestorTblName = $this->getNodeAncestorTableName();
            $id_curr = $this->getLinkIdCurrentAttribute();
            $id_ancestor = $this->getLinkIdAncestorAttribute();
            $sql = "INSERT INTO " . $this->getNodeAncestorTableName()
                    . " ($id_curr, $id_ancestor) "
                    . " (SELECT :id, $id_ancestor FROM $ancestorTblName "
                    . " WHERE $id_curr = :idParent)";
            $this->getDb()
                    ->createCommand($sql)
                    ->bindValue(":idParent", $this->{$this->getIdParentAttribute()}, PDO::PARAM_INT)
                    ->bindValue(":id", $this->id, PDO::PARAM_INT)
                    ->execute();
            $sql = "INSERT INTO " . $this->getNodeAncestorTableName()
                    . " ($id_curr, $id_ancestor) VALUES(:id, :idParent)";
            $this->getDb()
                    ->createCommand($sql)
                    ->bindValue(":idParent", $this->{$this->getIdParentAttribute()}, PDO::PARAM_INT)
                    ->bindValue(":id", $this->id, PDO::PARAM_INT)
                    ->execute();
        }

        return true;
    }

    protected function removeAncestorsLinks() {
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdCurrentAttribute() . " = :id";
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

    protected function removeMeAsMyDescendantsAncestor() {
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

    protected function getDirtyParentId() {
        $idParentAttribute = $this->getIdParentAttribute();
        if (isset($this->dirtyAttributes) && isset($this->dirtyAttributes[$idParentAttribute]))
            return $this->dirtyAttributes[$idParentAttribute];
        else
            return false;
    }

}
