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
use yii\helpers\ArrayHelper;

/**
 * Description of Node
 *
 * @author CodiVerum
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
            if ($this->isNewRecord) {
                $this->setNodeLevel($this->calculateLevel());
                if (parent::save($runValidation, $attributeNames)) {
                    $this->setAncestorsLinks();
                    $result = true;
                }
            } else if ($this->{$this->getIdParentAttribute()} != $this->getOldParentId() && $this->prepareParentChange($withSubtree) && parent::save($runValidation, $attributeNames)) {
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
            throw $e;
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
            throw $e;
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
     * Returns node level attribute name
     * @return string
     */
    public function getNodeLevelAttribute() {
        return $this->tableName() . "_level";
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
                        ->from(['ancestors' => static::tableName()])
                        ->viaTable($this->getNodeAncestorTableName(), [$this->getLinkIdCurrentAttribute() => 'id']);
    }

    /**
     * @param integer $distance
     * @return ActiveQuery
     */
    public function getAncestorByDistance($distance) {
        $linkIdCurrent = $this->getLinkIdCurrentAttribute();
        $currLevel = $this->{$this->getNodeLevelAttribute()};
        $levelWanted = $currLevel - $distance;
        if ($levelWanted <= 0)
            return null;

        return static::find()
                        ->joinWith(['ancestors'])
                        ->andWhere([$this->getNodeLevelAttribute() => $levelWanted])
                        ->andWhere([$this->getNodeAncestorTableName() . ".$linkIdCurrent" => $this->id]);
    }

    /**
     * @param integer $distance
     * @return ActiveQuery
     */
    public function getDescendantsByDistance($distance) {
        $linkIdAncestor = $this->getLinkIdAncestorAttribute();
        $currLevel = $this->{$this->getNodeLevelAttribute()};
        $levelWanted = $currLevel - $distance;
        if ($levelWanted <= 0)
            return null;

        return static::find()
                        ->joinWith(['ancestors'])
                        ->andWhere([$this->getNodeLevelAttribute() => $levelWanted])
                        ->andWhere([$this->getNodeAncestorTableName() . ".$linkIdAncestor" => $this->id]);
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

    /**
     * 
     * @return int[] Ancestor ids
     */
    public function getAncestorsIds() {
        $sql = "SELECT " . $this->getLinkIdAncestorAttribute()
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdCurrentAttribute() . "= :id";
        $res = $this->getDb()
                ->createCommand($sql)
                ->bindValue(":id", $this->id, PDO::PARAM_INT)
                ->queryAll();
        return ArrayHelper::getColumn($res, $this->getLinkIdAncestorAttribute());
    }

    /**
     * 
     * @return int[] Descentands ids
     */
    public function getDescendantsIds() {
        $sql = "SELECT " . $this->getLinkIdCurrentAttribute()
                . " FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() . "= :id";
        $res = $this->getDb()
                ->createCommand($sql)
                ->bindValue(":id", $this->id, PDO::PARAM_INT)
                ->queryAll();
        return ArrayHelper::getColumn($res, $this->getLinkIdCurrentAttribute());
    }

    /**
     * 
     * @return integer number of rows affected
     */
    public function removeDescendants() {
        $sql = "DELETE n FROM " . $this->tableName()
                . " n JOIN " . $this->getNodeAncestorTableName() . " na "
                . " ON na." . $this->getLinkIdCurrentAttribute() . " = n.id "
                . " WHERE na." . $this->getLinkIdAncestorAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    /**
     * 
     * @param boolean $withSubtree whether subtree should follow changes
     * @return boolean
     */
    protected function prepareParentChange($withSubtree) {
        $this->setNodeLevel($this->calculateLevel());
        if ($withSubtree) {
            $this->removeMyAncestorsFromMyDescendants();
            $this->removeAncestorsLinks();
            $this->setAncestorsLinks();
            $this->setMyAncestorsToMyDescendants();
            $this->changeDescendantsLevel($this->calculateLevelChange());
            return true;
        } else {
            $this->changeMyChildrensParentToMyParent($this->getOldParentId());
            $this->removeMeAsMyDescendantsAncestor();
            $this->changeDescendantsLevel(-1);
            return true;
        }
    }

    /**
     * 
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
    }

    /**
     * @return integer number of rows affected
     */
    protected function removeAncestorsLinks() {
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdCurrentAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    /**
     * 
     * @return integer number of rows affected
     */
    protected function removeMyAncestorsFromMyDescendants() {
        $ancestorIds = implode(",", $this->getAncestorsIds());
        $descentantsIds = implode(", ", $this->getDescendantsIds());
        if (empty($descentantsIds) || empty($ancestorIds))
            return true;
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() . " IN ($ancestorIds)"
                . " AND " . $this->getLinkIdCurrentAttribute() . " IN ($descentantsIds)";
        return $this->getDb()->createCommand($sql)->execute();
    }

    /**
     * 
     * @return integer number of rows affected
     */
    protected function setMyAncestorsToMyDescendants() {
        $id_node = $this->getLinkIdCurrentAttribute();
        $id_anc_node = $this->getLinkIdAncestorAttribute();
        $anc_tbl = $this->getNodeAncestorTableName();
        $sql = "INSERT INTO $anc_tbl($id_node, $id_anc_node) "
                . "(SELECT mydesc.$id_node, myanc.$id_anc_node FROM "
                . "(SELECT $id_node, $id_anc_node FROM $anc_tbl WHERE $id_node = :id) myanc "
                . "JOIN "
                . "(SELECT $id_node, $id_anc_node FROM $anc_tbl WHERE $id_anc_node = :id) mydesc "
                . "ON (myanc.$id_node = mydesc.$id_anc_node))";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT);
    }

    /**
     * 
     * @param integer $idGrandparent
     * @return integer number of rows affected
     */
    protected function changeMyChildrensParentToGrandparent($idGrandparent) {
        $sql = "UPDATE " . $this->tableName() . " SET " . $this->getIdParentAttribute() . " = :id_parent WHERE " . $this->getIdParentAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->bindValue(":id_parent_node", $idGrandparent, PDO::PARAM_INT)
                        ->execute();
    }

    /**
     * 
     * @return integer number of rows affected
     */
    protected function removeMeAsMyDescendantsAncestor() {
        $sql = "DELETE FROM " . $this->getNodeAncestorTableName()
                . " WHERE " . $this->getLinkIdAncestorAttribute() . " = :id";
        return $this->getDb()->createCommand($sql)
                        ->bindValue(":id", $this->id, PDO::PARAM_INT)
                        ->execute();
    }

    /**
     * 
     * @return string
     */
    protected function getLinkIdCurrentAttribute() {
        return "id_" . $this->tableName();
    }

    /**
     * 
     * @return string
     */
    protected function getLinkIdAncestorAttribute() {
        return "id_ancestor_" . $this->tableName();
    }

    /**
     * 
     * @return integer|null
     */
    protected function getOldParentId() {
        $idParentAttribute = $this->getIdParentAttribute();
        if (isset($this->oldAttributes) && isset($this->oldAttributes[$idParentAttribute]))
            return $this->oldAttributes[$idParentAttribute];
        else
            return null;
    }

    /**
     * 
     * @param integer $level
     */
    protected function setNodeLevel($level) {
        $this->{$this->getNodeLevelAttribute()} = $level;
    }

    /**
     * 
     * @return int calculated level
     */
    protected function calculateLevel() {
        if ($this->{$this->getIdParentAttribute()} > 0) {
            return $this->getParent()->one()->{$this->getNodeLevelAttribute()} + 1;
        } else
            return 0;
    }

    /**
     * 
     * @return int calculated level change distance
     */
    protected function calculateLevelChange() {
        $oldLevel = ArrayHelper::getValue($this->oldAttributes, $this->getNodeLevelAttribute(), 0);
        return $this->calculateLevel() - $oldLevel;
    }

    /**
     * Changes descendants level by specified distance
     * @param integer $distance 1 adds one to all descendant's level
     * @return integer number of rows affected
     */
    protected function changeDescendantsLevel($distance) {
        $nLvlAttr = $this->getNodeLevelAttribute();
        $sql = "UPDATE {$this->tableName()} n JOIN {$this->getNodeAncestorTableName()} na "
                . " ON (n.id = na.{$this->getLinkIdCurrentAttribute()})"
                . " SET n.$nLvlAttr = n.$nLvlAttr + ($distance)"
                . " WHERE na.{$this->getLinkIdAncestorAttribute()} = :id";
        return $this->getDb()
                        ->createCommand($sql)
                        ->bindValue(':id', $this->id, PDO::PARAM_INT)
                        ->execute();
    }

}
