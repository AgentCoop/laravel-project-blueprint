<?php

namespace Tests\Fixture;

use PhpCrystal\Core\Component\Mvc\AbstractView;

class TestView extends AbstractView
{
    public function getData()
    {
        return [];
    }

    /**
     * @return string
    */
    public function testCompileBlateTemplate($tpl, $data)
    {
        return $this->renderBladeMarkup($tpl, $data);
    }
}