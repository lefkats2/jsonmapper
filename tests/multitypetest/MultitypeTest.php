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
        $this->assertEquals('stringvalue', $res->basictypes);
        $this->assertInternalType('string', $res->basictypes);
    }

    public function testMapsLastBasicTypeInMultitype()
    {
        $mapper = new \JsonMapper();
        $json = '{"basictypes":3.22}';
        $res = $mapper->map(json_decode($json), new MultitypeTest_Object());
        $this->assertInstanceOf(MultitypeTest_Object::class, $res);
        $this->assertEquals(3.22, $res->basictypes);
        $this->assertInternalType('double', $res->basictypes);
    }
}
