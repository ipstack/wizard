<?php

namespace Ipstack\Wizard\Builder;

interface BuilderInterface
{

    /**
     * @param string $file
     * @param array $options
     * @return void
     */
    public function build($file, $options=array());
}