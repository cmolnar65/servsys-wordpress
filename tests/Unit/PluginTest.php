<?php

use PHPUnit\Framework\TestCase;
use ServSys\Plugin;

final class PluginTest extends TestCase {
	public function test_plugin_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}
}
