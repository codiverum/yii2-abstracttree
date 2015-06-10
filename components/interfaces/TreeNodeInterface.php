<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\components\interfaces;

use codiverum\abstracttree\models\AbstractNode;

/**
 *
 * @author CodiVerum
 */
interface TreeNodeInterface {

    /**
     * @return ActiveQuery
     */
    public function getAncestors();

    /**
     * @return integer[]
     */
    public function getAncestorsIds();

    /**
     * @return ActiveQuery
     */
    public function getParent();

    /**
     * @return ActiveQuery
     */
    public function getChildren();

    /**
     * @return ActiveQuery
     */
    public function getSiblings();

    /**
     * @return ActiveQuery
     */
    public function getDescendants();

    /**
     * @return integer[]
     */
    public function getDescendantsIds();

    /**
     * @return integer number of rows affected
     */
    public function removeDescendants();

    /**
     * @param integer $distance
     * @return ActiveQuery
     */
    public function getAncestorByDistance($distance);

    /**
     * @param integer $distance
     * @return ActiveQuery
     */
    public function getDescendantsByDistance($distance);
}
