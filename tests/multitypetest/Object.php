<?php

class MultitypeTest_Object
{
    /**
     * @var string|int|float
     */
    public $basictypes;

    /**
     * @var string|int|float|null
     */
    public $basictypesnullable;

    /**
     * @var string|int|string[]|int[]
     */
    public $basictypesandarrays;

    /**
     * @var string[]|int[]|string|int
     */
    public $basictypesandarraysincorrectorder;
}
