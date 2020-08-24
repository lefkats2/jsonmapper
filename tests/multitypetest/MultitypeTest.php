<?php

require_once __DIR__ . '/Object.php';

class MultitypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Asserts that $field in $classname can or cannot be mapped from data and match $value.
     *
     * @param mixed[] $data the json data (will be converted to text using json_encode() and again to array using
     *                      json_decode().
     * @param string $field the name of the field to assert
     * @param mixed|null the mapped value to assert, if not specified $data[$field] is used (i.e. original data of the
     *                   field)
     * @param string|null $strict_type if null then $mapper->bStrictTypeChecking and $mapper->bStrictObjectTypeChecking are
     *                                 false otherwise, true and type of resulting value is asserted to be $strict_type or
     *                                 gettype($value??$data[$field]) if $strict_type is ''(an empty string).
     * @param bool $expect_to_fail specifies whether to assert that it can or it can't map the specified field.
     * @param string $classname the class that json data should be mapped into, defaults to
     *                          MultitypeTest_Object::class.
     */
    protected function assertFieldMapped(array $data, string $field, $value = null, string $strict_type = null, bool $expect_to_fail = false, string  $classname = null)
    {
        $classname = $classname ??MultitypeTest_Object::class;
        $mapper = new \JsonMapper();
        $mapper->bStrictObjectTypeChecking = !is_null($strict_type);
        $mapper->bStrictTypeChecking = !is_null($strict_type);
        $json = json_encode($data);
        try {
            $res = $mapper->map(json_decode($json), new $classname());
        } catch (\JsonMapper_Exception $err) {
            if ($expect_to_fail) {
                $this->assertTrue($expect_to_fail);
                return;
            }
            throw $err;
        }
        $this->assertInstanceOf($classname, $res);
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

    /**
     * Dataset 1,
     *
     * @return array // initdata, classname, field, types, strict, fail, types_decls
     */
    protected function dataObjectsAndFields_1()
    {
        $d=[];
        $o = MultitypeTest_Object::class;
        return [
            // initdata, classname, field, types, strict, fail, types_decls
            [$d, $o, 'basictypes', 'string|int|float', false, false, null],
            [$d, $o, 'basictypes', 'null', false, true, 'string|int|float'],
            [$d, $o, 'basictypesnullable', 'string|int|float|null', false, false,null],
            [$d, $o, 'basictypesandarrays', 'string|int|string[]|int[]', false, false,null],
            [$d, $o, 'basictypesandarrays', 'null', false, true,'string|int|string[]|int[]'],
            [$d, $o, 'basictypesandarraysincorrectorder', 'string[]|int[]|string|int', false, false,null],
            [$d, $o, 'basictypesandarraysincorrectorder', 'null', false, true,'string[]|int[]|string|int'],
            [$d, $o, 'anytypesandarrays', 'int|string|float|string[]|int[]|JsonMapperTest_Object[]|JsonMapperTest_Object|object|array', false, false, null],
            [$d, $o, 'anytypesandarrays', 'null', false, true, 'int|string|float|string[]|int[]|JsonMapperTest_Object[]|JsonMapperTest_Object|object|array'],
            [$d, $o, 'basictypes', 'string|int|float', true, false, null],
            [$d, $o, 'basictypes', 'null', true, true, 'string|int|float'],
            [$d, $o, 'basictypesnullable', 'string|int|float|null', true, false,null],
            [$d, $o, 'basictypesandarrays', 'string|int|string[]|int[]', true, false,null],
            [$d, $o, 'basictypesandarrays', 'null', true, true,'string|int|string[]|int[]'],
            [$d, $o, 'basictypesandarraysincorrectorder', 'string[]|int[]|string|int', true, false,null],
            [$d, $o, 'basictypesandarraysincorrectorder', 'null', true, true,'string[]|int[]|string|int'],
            [$d, $o, 'anytypesandarrays', 'int|string|float|string[]|int[]|JsonMapperTest_Object[]|JsonMapperTest_Object|object|array', true, false, null],
            [$d, $o, 'anytypesandarrays', 'null', true, true, 'int|string|float|string[]|int[]|JsonMapperTest_Object[]|JsonMapperTest_Object|object|array'],
        ];
    }

    /**
     * Fakes a value for a given (annotated) type.
     */
    protected function fakeValueFor($type)
    {
        $matches=[];
        if (preg_match('/(.*)\\[\\]/', $type, $matches)) {
            return $this->fakeValuesFor($matches[1]);
        }
        switch ($type) {
            case 'null':
            case 'NULL':
                return null;
            case 'string':
                return 'stringvalue';
            case 'int':
            case 'integer':
                return 3;
            case 'float':
            case 'double':
                return 9.42;
            case 'object':
                return new \StdClass();
            case 'array':
                return ['stringvalue',4,3.2];
        }
        return new $type();
    }

    /**
     * Fakes values for a given (annotated) type.
     */
    protected function fakeValuesFor($type)
    {
        return [$this->fakeValueFor($type)];
    }

    /**
     * Data provider for testMapsMultitype
     */
    public function dataForTestMaps()
    {
        foreach ($this->dataObjectsAndFields_1() as $data) {
            $types = explode('|', $data[3]);
            foreach ($types as $type) {
                foreach ($this->fakeValuesFor($type) as $value) {
                    $initdata = array_merge([], $data[0], [$data[2]=>$value]);
                    $value_t = json_encode($value);
                    $decls = $data[6] ?? $data[3];
                    if ($data[5]) {
                        yield [
                            'Does not map '.$value_t.' as "'.$type.'" in multitype decleration "'.$decls.'"'.($data[4]?' with strict type checking':''),
                            $initdata, $data[1], $data[2], $value, $data[4]?is_string($data[4])?$data[4]:'':null, $data[5]

                        ];
                    } else {
                        yield [
                            'Maps '.$value_t.' as "'.$type.'" in multitype decleration "'.$decls.'"'.($data[4]?' with strict type checking':''),
                            $initdata, $data[1], $data[2], $value, $data[4]?is_string($data[4])?$data[4]:'':null, $data[5]

                        ];
                    }
                }
            }
        }
    }

    /**
     * @dataProvider dataForTestMaps
     * @testdox $title
     */
    public function testMapsMultitype(string $title, array $data, string $classname, string $field, $value, string $strict_type = null, bool $expect_to_fail)
    {
        if (is_null($strict_type)) {
            $this->markTestSkipped('Multitype is only supported with bStrictTypeChecking=true until type order is fixed');
        }
        $this->assertFieldMapped($data, $field, $value, $strict_type, $expect_to_fail, $classname);
    }
}
