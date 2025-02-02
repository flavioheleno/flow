<?php

declare(strict_types=1);

namespace Flow\Parquet\Tests\Integration\IO;

use Flow\Parquet\Reader;
use PHPUnit\Framework\TestCase;

final class EdgeCasesReadingTest extends TestCase
{
    public function test_nonullable_impala() : void
    {
        $path = __DIR__ . '/Fixtures/EdgeCases/nonnullable.impala.parquet';

        $reader = (new Reader())->read($path);

        $rows = [];

        foreach ($reader->values() as $row) {
            $rows[] = $row;
        }

        self::assertSame(
            [
                [
                    'ID' => 8,
                    'Int_Array' => [
                        'list' => [
                            'element' => -1,
                        ],
                    ],
                    'int_array_array' => [
                        'list' => [
                            'element' => [
                                'list' => [
                                    'element' => [
                                        [-1, -2],
                                        [null],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Int_Map' => [
                        'map' => [
                            'key' => 'k1',
                            'value' => -1,
                        ],
                    ],
                    'int_map_array' => [
                        'list' => [
                            'element' => [
                                'map' => [
                                    'key' => [null, 'k1', null, null],
                                    'value' => [null, 1, null, null]],
                            ],
                        ],
                    ],
                    'nested_Struct' => [
                        'a' => -1,
                        'B' => [
                            'list' => [
                                'element' => -1,
                            ],
                        ],
                        'c' => [
                            'D' => [
                                'list' => [
                                    'element' => [
                                        'list' => [
                                            'element' => [
                                                'e' => -1,
                                                'f' => 'nonnullable',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'G' => null,
                    ],
                ],
            ],
            $rows
        );
    }

    public function test_read_datapage_v2_snappy_list() : void
    {
        $this->expectExceptionMessage('Encoding DELTA_BINARY_PACKED not supported');

        $path = __DIR__ . '/Fixtures/EdgeCases/datapage_v2.snappy.parquet';

        $reader = (new Reader())->read($path);

        $rows = [];

        foreach ($reader->values() as $row) {
            $rows[] = $row;
        }

        self::assertSame(
            [
                ['emptylist' => null],
            ],
            $rows
        );
    }

    public function test_read_null_list() : void
    {
        $path = __DIR__ . '/Fixtures/EdgeCases/null_list.parquet';

        $reader = (new Reader())->read($path);

        $rows = [];

        foreach ($reader->values() as $row) {
            $rows[] = $row;
        }

        self::assertSame(
            [
                ['emptylist' => null],
            ],
            $rows
        );
    }
}
