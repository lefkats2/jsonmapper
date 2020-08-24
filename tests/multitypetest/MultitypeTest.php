<?php

require_once __DIR__ . '/Object.php';

class MultitypeTest extends \PHPUnit\Framework\TestCase
{
    public function testMapsFirstBasicTypeInMultitype()
    {
        $mapper = new \JsonMapper();
        $json = '{"basictypes":"stringvalue"}';
        $res = $mapper->map(json_decode($json), new MultitypeTest_Object());
        $this->assertInstanceOf(MultitypeTest_Object::class, $res);
        $this->assertInternalType('string', $res->basictypes);
        $this->assertEquals('stringvalue', $res->basictypes);
    }
}
