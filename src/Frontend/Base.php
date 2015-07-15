<?php
namespace Garden\Porter\Frontend;

abstract class Base {
    public function __construct() {
        $this->setOpts();
    }

    abstract protected function setOpts();
}
