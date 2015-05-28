<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\components\interfaces;

use codiverum\abstracttree\model\AbstractNode;

/**
 *
 * @author jozek
 */
interface TreeNodeInterface {

    /**
     * @return AbstractNode[]
     */
    public function getAncestors();

    /**
     * @return integer[]
     */
    public function getAncestorsIds();

    /**
     * @return AbstractNode
     */
    public function getParent();

    /**
     * @return AbstractNode[]
     */
    public function getChildren();

    /**
     * @return AbstractNode[]
     */
    public function getSiblings();

    /**
     * @return AbstractNode[]
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
     * @return AbstractNode
     */
    public function getAncestorByDistance($distance);

    /**
     * @param integer $distance
     * @return AbstractNode[]
     */
    public function getDescendantsByDistance($distance);
}
