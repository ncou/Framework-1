<?php
namespace Wandu\Http\Parameters;

use Mockery;
use PHPUnit_Framework_TestCase;

class ParameterTest extends PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $params = new Parameter([
            'string' => 'string!',
            'number' => '10',
        ]);

        $this->assertSame('string!', $params->get('string'));
        $this->assertSame('10', $params->get('number'));

        $this->assertNull($params->get('string.undefined'));
        $this->assertNull($params->get('number.undefined'));
    }

    public function testGetNull()
    {
        $params = new Parameter([
            'null' => null,
        ]);
        $this->assertNull($params->get('null', "Other Value!!"));
    }

    public function testHas()
    {
        $params = new Parameter([
            'string' => 'string!',
            'number' => '10',
        ]);

        $this->assertTrue($params->has('string'));
        $this->assertTrue($params->has('number'));

        $this->assertFalse($params->has('string.undefined'));
        $this->assertFalse($params->has('number.undefined'));
    }

    public function testHasNull()
    {
        $params = new Parameter([
            'null' => null,
        ]);
        $this->assertTrue($params->has('null'));
    }

    public function testToArray()
    {
        $params = new Parameter([
            'null' => null,
        ]);
        $this->assertSame([
            'null' => null,
        ], $params->toArray());
    }

    public function testToArrayWithFallback()
    {
        $fallbacks = new Parameter([
            'string1' => 'string 1 fallback!',
            'fallback' => 'fallback!',
        ]);
        $params = new Parameter([
            'string1' => 'string 1!',
            'string2' => 'string 2!',
        ], $fallbacks);

        $this->assertSame([
            'string1' => 'string 1!',
            'string2' => 'string 2!',
            'fallback' => 'fallback!',
        ], $params->toArray());
    }

    public function testGetWithDefault()
    {
        $params = new Parameter([
            'string' => 'string!',
            'number' => '10',
        ]);

        $this->assertSame("default", $params->get('string.undefined', "default"));
        $this->assertSame("default", $params->get('number.undefined', "default"));
    }

    public function testFallback()
    {
        $fallbacks = new Parameter([
            'string1' => 'string 1 fallback!',
            'fallback' => 'fallback!',
        ]);
        $params = new Parameter([
            'string1' => 'string 1!',
            'string2' => 'string 2!',
        ], $fallbacks);

        $this->assertSame("string 1!", $params->get('string1'));
        $this->assertSame("string 2!", $params->get('string2'));
        $this->assertSame("fallback!", $params->get('fallback'));
        $this->assertSame(null, $params->get('undefined'));

        $this->assertSame("string 1!", $params->get('string1', "default"));
        $this->assertSame("string 2!", $params->get('string2', "default"));
        $this->assertSame("fallback!", $params->get('fallback', "default"));
        $this->assertSame("default", $params->get('undefined', "default"));
    }

    public function testHasWithFallback()
    {
        $fallbacks = new Parameter([
            'string1' => 'string 1 fallback!',
            'fallback' => 'fallback!',
        ]);
        $params = new Parameter([
            'string1' => 'string 1!',
            'string2' => 'string 2!',
        ], $fallbacks);

        $this->assertTrue($params->has('string1'));
        $this->assertTrue($params->has('string2'));
        $this->assertTrue($params->has('fallback'));
        $this->assertFalse($params->has('undefined'));
    }

    /**
     * @dataProvider castingProvider
     */
    public function testCasting($input, $cast, $output)
    {
        $params = new Parameter([
            'array' => $input,
        ]);

        $this->assertSame($input, $params->get('array'));
        $this->assertSame($output, $params->get('array', [], $cast));
    }

    public function testToaArrayWithCasting()
    {
        $values = $this->castingProvider();

        // always true!!!!
        for ($i = 0; $i < 100; $i++) {
            $castKey1 = $values[rand(0, count($values) - 1)];
            $castKey2 = $values[rand(0, count($values) - 1)];

            $noCastKey1 = $values[rand(0, count($values) - 1)];
            $noCastKey2 = $values[rand(0, count($values) - 1)];

            $params = new Parameter([
                'key1' => $castKey1[0],
                'key2' => $castKey2[0],
                'key3' => $noCastKey1[0],
                'key4' => $noCastKey2[0],
            ]);

            $this->assertSame([
                'key1' => $castKey1[2],
                'key2' => $castKey2[2],
                'key3' => $noCastKey1[0],
                'key4' => $noCastKey2[0],
            ], $params->toArray([
                'key1' => $castKey1[1],
                'key2' => $castKey2[1],
            ]));
        }
    }

    public function castingProvider()
    {
        return [
            [['10', '20', '30'], 'int[]', [10, 20, 30]],
            [['10', '20', '30'], 'integer[]', [10, 20, 30]],
            [['10', '20', '30'], 'string[]', ['10', '20', '30']],
            [['10', '20', '30'], 'array', ['10', '20', '30']],
            [['10', '20', '30'], 'string', '10,20,30'],

            ['10,20,30', 'int[]', [10, 20, 30]],
            ['10,20,30', 'integer[]', [10, 20, 30]],
            ['10,20,30', 'string[]', ['10', '20', '30']],
            ['10,20,30', 'array', ['10', '20', '30']],
            ['10,20,30', 'string', '10,20,30'],

            ['10', 'int[]', [10]],
            ['10', 'integer[]', [10]],
            ['10', 'string[]', ['10']],
            ['10', 'array', ['10']],
            ['10', 'string', '10'],

            ['10', 'int', 10],
            ['10', 'number', 10.0],
            ['10', 'float', 10.0],
            ['10', 'double', 10.0],
            ['10', 'bool', true],
            ['10', 'boolean', true],
            ['false', 'boolean', false],
            ['true', 'boolean', true],
        ];
    }
}
