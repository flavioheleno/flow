<?php

declare(strict_types=1);

use function Flow\ETL\DSL\{data_frame, df, int_entry, str_entry, to_stream};
use Flow\ETL\Join\Comparison\Equal;
use Flow\ETL\Join\{Expression, Join};
use Flow\ETL\{DataFrame, DataFrameFactory, Extractor, FlowContext, Row, Rows};

require __DIR__ . '/../../../autoload.php';

$apiExtractor = new class implements Extractor {
    public function extract(FlowContext $context) : Generator
    {
        yield new Rows(
            Row::create(int_entry('id', 1), str_entry('sku', 'PRODUCT01')),
            Row::create(int_entry('id', 2), str_entry('sku', 'PRODUCT02')),
            Row::create(int_entry('id', 3), str_entry('sku', 'PRODUCT03'))
        );

        yield new Rows(
            Row::create(int_entry('id', 10_001), str_entry('sku', 'PRODUCT10_001')),
            Row::create(int_entry('id', 10_002), str_entry('sku', 'PRODUCT10_002')),
            Row::create(int_entry('id', 10_003), str_entry('sku', 'PRODUCT10_003'))
        );
    }
};

$db_data_frame = new class implements DataFrameFactory {
    public function from(Rows $rows) : DataFrame
    {
        return df()->process($this->findRowsInDatabase($rows));
    }

    private function findRowsInDatabase(Rows $rows) : Rows
    {
        // Lets pretend there are 10k more entries in the DB
        $rowsFromDb = \array_map(
            static fn (int $id) : Row => Row::create(int_entry('id', $id), str_entry('sku', 'PRODUCT' . $id)),
            \range(1, 10_000)
        );

        return (new Rows(...$rowsFromDb))
            // this would be a database SQL query in real life
            ->filter(fn (Row $row) => \in_array($row->valueOf('id'), $rows->reduceToArray('id'), true));
    }
};

/**
 * DataFrame::joinEach in some cases might become more optimal choice, especially when
 * right size is much bigger then a left side. In that case it's better to reduce the ride side
 * by fetching from the storage only what is relevant for the left side.
 */
data_frame()
    ->extract($apiExtractor)
    ->joinEach(
        $db_data_frame,
        Expression::on(new Equal('id', 'id')), // by using All or Any comparisons, more than one entry can be used to prepare the condition
        Join::left_anti
    )
    ->write(to_stream(__DIR__ . '/output.txt', truncate: false))
    ->run();
