<?php

/**
 * DataGrab Relationship fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_rel extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		$this->EE->db->where( 'channel_id', $this->EE->api_channel_fields->settings[ $field_id ][ 'field_related_id' ] );
		$this->EE->db->where( 'title', $DG->datatype->get_item( $item, $DG->settings["cf"][ $field ] ) );
		$this->EE->db->select( 'entry_id' );
		$query = $this->EE->db->get( 'exp_channel_titles' );
		if( $query->num_rows() > 0 ) {
			$row = $query->row_array();
			$data[ "field_id_" . $field_id ] = $row[ "entry_id" ];
		}

	}

}

?>