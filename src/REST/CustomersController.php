<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ServSys\REST;

use ServSys\Customers\CustomerRepository;
use ServSys\Customers\CustomerService;
use ServSys\Sites\SiteRepository;
use ServSys\Users\Capabilities;

final class CustomersController {
	public function register_routes(): void {
		register_rest_route(
			'servsys/v1',
			'/customers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => function () { return current_user_can( Capabilities::VIEW_CUSTOMERS ); },
					'args'                => array( 'search' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => function () { return current_user_can( Capabilities::CREATE_CUSTOMERS ); },
				),
			)
		);

		register_rest_route(
			'servsys/v1',
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'show' ),
				'permission_callback' => function () { return current_user_can( Capabilities::VIEW_CUSTOMERS ); },
			)
		);
	}

	public function index( \WP_REST_Request $request ) {
		return rest_ensure_response( ( new CustomerRepository() )->search( (string) $request->get_param( 'search' ) ) );
	}

	public function show( \WP_REST_Request $request ) {
		$id       = absint( $request['id'] );
		$customer = ( new CustomerRepository() )->find( $id );
		if ( ! $customer ) {
			return new \WP_Error( 'servsys_not_found', 'Customer not found.', array( 'status' => 404 ) );
		}
		$customer['sites'] = ( new SiteRepository() )->for_customer( $id, false );
		return rest_ensure_response( $customer );
	}

	public function create( \WP_REST_Request $request ) {
		try {
			$id = ( new CustomerService() )->save( (array) $request->get_json_params() );
			return new \WP_REST_Response( ( new CustomerRepository() )->find( $id ), 201 );
		} catch ( \InvalidArgumentException $exception ) {
			return new \WP_Error( 'servsys_invalid_customer', $exception->getMessage(), array( 'status' => 400 ) );
		}
	}
}
