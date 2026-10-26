<?php
namespace ServSys\Companies;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CompanyService {
	private $repository;
	public function __construct( CompanyRepository $repository = null ) { $this->repository=$repository ?: new CompanyRepository(); }
	public function save( array $input, int $id = 0 ): int {
		$name=sanitize_text_field( $input['name'] ?? '' ); if ( '' === $name ) { throw new \InvalidArgumentException( 'Company name is required.' ); }
		$month=absint( $input['fiscal_year_end_month'] ?? 12 ); $day=absint( $input['fiscal_year_end_day'] ?? 31 );
		if ( ! checkdate( $month, $day, 2000 ) ) { throw new \InvalidArgumentException( 'Fiscal year end is not valid.' ); }
		$now=current_time( 'mysql', true ); $user=get_current_user_id();
		$data=array(
			'name'=>$name, 'logo_attachment_id'=>absint( $input['logo_attachment_id'] ?? 0 ) ?: null,
			'tagline'=>sanitize_textarea_field( $input['tagline'] ?? '' ), 'address1'=>sanitize_text_field( $input['address1'] ?? '' ),
			'address2'=>sanitize_text_field( $input['address2'] ?? '' ), 'city'=>sanitize_text_field( $input['city'] ?? '' ),
			'state'=>sanitize_text_field( $input['state'] ?? '' ), 'postal_code'=>sanitize_text_field( $input['postal_code'] ?? '' ),
			'phone'=>sanitize_text_field( $input['phone'] ?? '' ), 'use_preprinted_letterhead'=>empty( $input['use_preprinted_letterhead'] ) ? 0 : 1,
			'fiscal_year_end_month'=>$month, 'fiscal_year_end_day'=>$day, 'customer_portal_enabled'=>empty( $input['customer_portal_enabled'] ) ? 0 : 1,
			'active'=>empty( $input['active'] ) ? 0 : 1, 'updated_at'=>$now, 'updated_by'=>$user ?: null,
		);
		if ( $id ) { if ( ! $this->repository->find_unscoped( $id ) || ! $this->repository->update( $id, $data ) ) { throw new \RuntimeException( 'Unable to update company.' ); } return $id; }
		$data['created_at']=$now; $data['created_by']=$user ?: null; $id=$this->repository->create( $data ); if ( ! $id ) { throw new \RuntimeException( 'Unable to create company.' ); } return $id;
	}
}
