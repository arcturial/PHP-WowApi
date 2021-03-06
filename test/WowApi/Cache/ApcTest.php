<?php

use WowApi\Cache\Apc;

class ApcTest extends AbstractCacheTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!function_exists('apc_store')) {
            $this->markTestSkipped('APC is not installed');
        }
    }

    function getCacheAdaptor()
    {
        return new Apc();
    }
}
?>
