<?php

declare(strict_types=1);

namespace Tests;

use App\Cache\ArrayKeyValueStore;
use PHPUnit\Framework\TestCase;

class ArrayKeyValueStoreTest extends TestCase
{
    public function testIncrementRespeitaTtl(): void
    {
        $store = new ArrayKeyValueStore();
        $store->clear();

        $this->assertSame(1, $store->increment('contador', 1));
        $this->assertSame(2, $store->increment('contador', 1));
        $this->assertGreaterThanOrEqual(0, $store->ttl('contador'));

        sleep(2);

        $this->assertNull($store->get('contador'));
        $this->assertSame(1, $store->increment('contador', 1));
    }

    public function testSetDeleteEGetFuncionam(): void
    {
        $store = new ArrayKeyValueStore();
        $store->clear();

        $this->assertTrue($store->set('chave', ['ok' => true], 10));
        $this->assertSame(['ok' => true], $store->get('chave'));
        $this->assertTrue($store->delete('chave'));
        $this->assertNull($store->get('chave'));
    }
}
