<?php

namespace Lumus\Engine\Classes\ColRender;



class ColRender
{


    /**
     * @var callable(ColRenderContext $context): strgin|null
     */
    public $renderFunction = null;


    /**
     * @var callable(ColRenderContext $context): strgin|null
     */
    public $popupRenderFunction = null;

    public $usePopup = false;



    /**
     * @param callable(ColRenderContext $context): mixed $handler
     * @return self
     */
    public function popup(callable $handler): static
    {




        $this->popupRenderFunction = $handler;
        return $this;
    }



    /**
     * @param callable(ColRenderContext $context): mixed $handler
     * @return self
     */
    public function render(callable $handler): static
    {



        $this->renderFunction = $handler;
        return $this;
    }

    public $popup = null;
    public $render = null;

    public function make($context) {}
}
