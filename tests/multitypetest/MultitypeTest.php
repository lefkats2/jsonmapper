<?php

require_once __DIR__ . '/Object.php';

class MultitypeTest extends \PHPUnit\Framework\TestCase
{
    protected function assertFieldMapped(array $data, string $field, string $value = null, string $strict_type = null, bool $expect_to_fail = false)
    {
        $mapper = new \JsonMapper();
        $mapper->bStrictObjectTypeChecking = !is_null($strict_type);
        $mapper->bStrictTypeChecking = !is_null($strict_type);
        $json = json_encode($data);
        try {
            $res = $mapper->map(json_decode($json), new MultitypeTest_Object());
        } catch (\JsonMapper_Exception $err) {
            if ($expect_to_fail) {
                $this->assertTrue($expect_to_fail);
                return;
            }
            throw $err;
        }
        $this->assertInstanceOf(MultitypeTest_Object::class, $res);
        $this->assertEquals($value ?? $data[$field], $res->{$field});
        if (!is_null($strict_type)) {
            if (empty($strict_type)) {
                $strict_type = gettype($data[$field]);
                if ($strict_type=='NULL') {
                    $strict_type='null';
                }
            }
            $this->assertInternalType($strict_type, $res->{$field});
        }
        if ($expect_to_fail) {
            $this->fail("Mapping should have failed but didn't");
        }
    }

    public function testMapsFirstBasicTypeInMultitype()
    {
        $data = ["basictypes"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypes');
    }

    public function testMapsFirstBasicTypeInMultitypeStrictType()
    {
        $data = ["basictypes"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypes', null, '');
    }

    public function testMapsMiddleBasicTypeInMultitype()
    {
        $data = ["basictypes"=>144];
        $this->assertFieldMapped($data, 'basictypes');
    }

    public function testMapsMiddleBasicTypeInMultitypeStrictType()
    {
        $data = ["basictypes"=>144];
        $this->assertFieldMapped($data, 'basictypes', null, '');
    }

    public function testMapsLastBasicTypeInMultitype()
    {
        $data = ["basictypes"=>3.22];
        $this->assertFieldMapped($data, 'basictypes');
    }

    public function testMapsLastBasicTypeInMultitypeStrictType()
    {
        $data = ["basictypes"=>3.22];
        $this->assertFieldMapped($data, 'basictypes', null, '');
    }
    public function testDoesNotMapNullBasicTypeInMultitype()
    {
        $data = ["basictypes"=>null];
        $this->assertFieldMapped($data, 'basictypes', null, null, true);
    }

    public function testDoesNotMapNullBasicTypeInMultitypeStrictType()
    {
        $data = ["basictypes"=>null];
        $this->assertFieldMapped($data, 'basictypes', null, '', true);
    }

    // basictypesnullable

    public function testMapsFirstBasicTypeNullableInMultitype()
    {
        $data = ["basictypes"=>"stringvalue","basictypesnullable"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesnullable');
    }

    public function testMapsFirstBasicTypeNullableInMultitypeStrictType()
    {
        $data = ["basictypes"=>"stringvalue","basictypesnullable"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }

    public function testMapsMiddleBasicTypeNullableInMultitype()
    {
        $data = ["basictypes"=>144,"basictypesnullable"=>144];
        $this->assertFieldMapped($data, 'basictypesnullable');
    }

    public function testMapsMiddleBasicTypeNullableInMultitypeStrictType()
    {
        $data = ["basictypes"=>144,"basictypesnullable"=>144];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }

    public function testMapsLastBasicTypeNullableInMultitype()
    {
        $data = ["basictypes"=>3.22,"basictypesnullable"=>3.22];
        $this->assertFieldMapped($data, 'basictypesnullable');
    }

    public function testMapsLastBasicTypeNullableInMultitypeStrictType()
    {
        $data = ["basictypes"=>3.22,"basictypesnullable"=>3.22];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }
    public function testMapsNullBasicTypeNullableInMultitype()
    {
        $data = ["basictypes"=>0,"basictypesnullable"=>null];
        $this->assertFieldMapped($data, 'basictypesnullable', null);
    }

    public function testMapsNullBasicTypeNullableInMultitypeStrictType()
    {
        $data = ["basictypes"=>0,"basictypesnullable"=>null];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }
}
