<?php

namespace Ipstack\Wizard\Builder;

interface BuilderInterface
{

    /**
     * @param string $file
     * @return void
     */
    public function build($file);
}