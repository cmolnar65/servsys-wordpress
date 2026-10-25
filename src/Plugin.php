<?php

namespace ServSys;

final class Plugin {
	private static $instance;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_hooks(): void {
		// Phase 4 establishes the plugin bootstrap only.
		// Functional hooks are added in later implementation phases.
	}
}
