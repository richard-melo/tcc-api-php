<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

class HeavyStringTest extends FunctionalTestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/phpunit_heavy_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider stringTransformProvider
     */
    public function testStringTransformations(
        string $input,
        string $expectedUpper,
        string $expectedLower,
        int $expectedLength,
    ): void {
        usleep(10_000);

        $path    = $this->tmpDir . '/' . uniqid('str_', true) . '.json';
        $payload = json_encode(['input' => $input, 'ts' => microtime(true)]);
        file_put_contents($path, (string) $payload);
        $recovered = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($recovered);
        $this->assertEquals($input, $recovered['input']);
        $this->assertEquals($expectedUpper, strtoupper($input));
        $this->assertEquals($expectedLower, strtolower($input));
        $this->assertEquals($expectedLength, strlen($input));
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function stringTransformProvider(): array
    {
        $words = [
            'apple', 'banana', 'cherry', 'dragonfruit', 'elderberry',
            'fig', 'grape', 'honeydew', 'kiwi', 'lemon',
            'mango', 'nectarine', 'orange', 'papaya', 'quince',
            'raspberry', 'strawberry', 'tangerine', 'ugli', 'watermelon',
            'apricot', 'blueberry', 'cantaloupe', 'date', 'guava',
            'jackfruit', 'kumquat', 'lychee', 'mulberry', 'olive',
            'peach', 'pear', 'plum', 'pomegranate', 'starfruit',
            'avocado', 'coconut', 'durian', 'feijoa', 'gooseberry',
            'huckleberry', 'ackee', 'bilberry', 'boysenberry', 'clementine',
            'damson', 'dewberry', 'elderflower', 'lingonberry', 'loganberry',
        ];
        $cases = [];
        foreach ($words as $word) {
            $cases[$word] = [$word, strtoupper($word), strtolower($word), strlen($word)];
        }
        return $cases;
    }
}
