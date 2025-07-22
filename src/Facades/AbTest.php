<?php

namespace Homemove\AbTesting\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string variant(string $experimentName, $userId = null)
 * @method static bool isVariant(string $experimentName, string $variantName, $userId = null)
 * @method static void track(string $experimentName, $userId = null, string $eventName = 'conversion', array $properties = [])
 * @method static void clearCache(string $experimentName = null)
 */
class AbTest extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ab-testing';
    }
}