<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace codiverum\abstracttree\components\interfaces;

/**
 *
 * @author jozek
 */
interface TreeNodeInterface {

    public function getParent();

    public function getAncestors();

    public function getChildren();
    
    public function getSiblings();
}
