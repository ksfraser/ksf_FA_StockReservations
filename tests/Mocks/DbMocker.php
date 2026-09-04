<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations\tests\Mocks;

/**
 * Mock DB functions for testing without FA environment.
 *
 * @since 1.0.0
 */
class DbMocker
{
    private static $results = [];
    private static $insertId = 1;
    private static $queryCount = 0;

    public static function reset(): void
    {
        self::$results = [];
        self::$insertId = 1;
        self::$queryCount = 0;
    }

    public static function setMockResult(string $sql, array $result): void
    {
        self::$results[self::$queryCount] = $result;
    }

    public static function setResults(array $results): void
    {
        self::$results = $results;
    }

    public static function setInsertId(int $id): void
    {
        self::$insertId = $id;
    }

    public static function getLastInsertId(): int
    {
        return self::$insertId++;
    }

    public static function incrementQuery(): int
    {
        return self::$queryCount++;
    }
}

function db_query($sql, $err = '')
{
    DbMocker::incrementQuery();
    $idx = DbMocker::getLastQueryIndex();
    if (isset(DbMocker::$results[$idx])) {
        return $idx;
    }
    return null;
}

function db_fetch_assoc($result)
{
    if ($result === null) {
        return false;
    }
    $idx = (int) $result;
    if (isset(DbMocker::$results[$idx][DbMocker::$currentRow[$idx] ?? 0])) {
        return DbMocker::$results[$idx][DbMocker::$currentRow[$idx]++];
    }
    return false;
}

function db_insert_id()
{
    return DbMocker::getLastInsertId();
}

class DbMockerWrapper extends DbMocker
{
    public static $currentRow = [];

    public static function getLastQueryIndex(): int
    {
        return self::$queryCount - 1;
    }
}