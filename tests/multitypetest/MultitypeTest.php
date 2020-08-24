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

    public function testMapsFirstBasicTypeInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypes', null, '');
    }

    public function testMapsMiddleBasicTypeInMultitype()
    {
        $data = ["basictypes"=>144];
        $this->assertFieldMapped($data, 'basictypes');
    }

    public function testMapsMiddleBasicTypeInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>144];
        $this->assertFieldMapped($data, 'basictypes', null, '');
    }

    public function testMapsLastBasicTypeInMultitype()
    {
        $data = ["basictypes"=>3.22];
        $this->assertFieldMapped($data, 'basictypes');
    }

    public function testMapsLastBasicTypeInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>3.22];
        $this->assertFieldMapped($data, 'basictypes', null, '');
    }
    public function testDoesNotMapNullBasicTypeInMultitype()
    {
        $data = ["basictypes"=>null];
        $this->assertFieldMapped($data, 'basictypes', null, null, true);
    }

    public function testDoesNotMapNullBasicTypeInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>null];
        $this->assertFieldMapped($data, 'basictypes', null, '', true);
    }

    // basictypesnullable

    public function testMapsFirstBasicTypeInNullableMultitype()
    {
        $data = ["basictypes"=>"stringvalue","basictypesnullable"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesnullable');
    }

    public function testMapsFirstBasicTypeInNullableMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>"stringvalue","basictypesnullable"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }

    public function testMapsMiddleBasicTypeInNullableMultitype()
    {
        $data = ["basictypes"=>144,"basictypesnullable"=>144];
        $this->assertFieldMapped($data, 'basictypesnullable');
    }

    public function testMapsMiddleBasicTypeInNullableMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>144,"basictypesnullable"=>144];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }

    public function testMapsLastBasicTypeInNullableMultitype()
    {
        $data = ["basictypes"=>3.22,"basictypesnullable"=>3.22];
        $this->assertFieldMapped($data, 'basictypesnullable');
    }

    public function testMapsLastBasicTypeInNullableMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>3.22,"basictypesnullable"=>3.22];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }
    public function testMapsNullBasicTypeInNullableMultitype()
    {
        $data = ["basictypes"=>0,"basictypesnullable"=>null];
        $this->assertFieldMapped($data, 'basictypesnullable', null);
    }

    public function testMapsNullBasicTypeInNullableMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>0,"basictypesnullable"=>null];
        $this->assertFieldMapped($data, 'basictypesnullable', null, '');
    }

    // basictypesandarrays

    public function testMapsFirstBasicTypeOrArrayInMultitype()
    {
        $data = ["basictypes"=>"stringvalue","basictypesandarrays"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesandarrays');
    }

    public function testMapsFirstBasicTypeOrArrayInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>"stringvalue","basictypesandarrays"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesandarrays', null, '');
    }

    public function testMapsSecondBasicTypeOrArrayInMultitype()
    {
        $data = ["basictypes"=>144,"basictypesandarrays"=>144];
        $this->assertFieldMapped($data, 'basictypesandarrays');
    }

    public function testMapsSecondBasicTypeOrArrayInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>144,"basictypesandarrays"=>144];
        $this->assertFieldMapped($data, 'basictypesandarrays', null, '');
    }

    public function testMapsThirdBasicTypeOrArrayInMultitype()
    {
        $data = ["basictypes"=>3.22,"basictypesandarrays"=>["stringvalue"]];
        $this->assertFieldMapped($data, 'basictypesandarrays');
    }

    public function testMapsThirdBasicTypeOrArrayInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>3.22,"basictypesandarrays"=>["stringvalue"]];
        $this->assertFieldMapped($data, 'basictypesandarrays', null, '');
    }
    public function testMapsFourthBasicTypeOrArrayInMultitype()
    {
        $data = ["basictypes"=>0,"basictypesandarrays"=>[444]];
        $this->assertFieldMapped($data, 'basictypesandarrays');
    }

    public function testMapsFourthBasicTypeOrArrayInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>0,"basictypesandarrays"=>[444]];
        $this->assertFieldMapped($data, 'basictypesandarrays', null, '');
    }

    // basictypesandarraysincorrectorder

    public function testMapsFirstBasicTypeOrArrayInCorrectOrderInMultitype()
    {
        $data = ["basictypes"=>"stringvalue","basictypesandarraysincorrectorder"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder');
    }

    public function testMapsFirstBasicTypeOrArrayInCorrectOrderInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>"stringvalue","basictypesandarraysincorrectorder"=>"stringvalue"];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder', null, '');
    }

    public function testMapsSecondBasicTypeOrArrayInCorrectOrderInMultitype()
    {
        $data = ["basictypes"=>144,"basictypesandarraysincorrectorder"=>144];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder');
    }

    public function testMapsSecondBasicTypeOrArrayInCorrectOrderInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>144,"basictypesandarraysincorrectorder"=>144];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder', null, '');
    }

    public function testMapsThirdBasicTypeOrArrayInCorrectOrderInMultitype()
    {
        $data = ["basictypes"=>3.22,"basictypesandarraysincorrectorder"=>["stringvalue"]];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder');
    }

    public function testMapsThirdBasicTypeOrArrayInCorrectOrderInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>3.22,"basictypesandarraysincorrectorder"=>["stringvalue"]];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder', null, '');
    }
    public function testMapsFourthBasicTypeOrArrayInCorrectOrderInMultitype()
    {
        $data = ["basictypes"=>0,"basictypesandarraysincorrectorder"=>[444]];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder');
    }

    public function testMapsFourthBasicTypeOrArrayInCorrectOrderInMultitypeWithStrictTypeCheck()
    {
        $data = ["basictypes"=>0,"basictypesandarraysincorrectorder"=>[444]];
        $this->assertFieldMapped($data, 'basictypesandarraysincorrectorder', null, '');
    }
}
