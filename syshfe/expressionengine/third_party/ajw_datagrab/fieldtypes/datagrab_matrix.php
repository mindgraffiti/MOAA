<?php

/**
 * DataGrab Matrix fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_matrix extends Datagrab_fieldtype {

	function register_setting( $field_name ) {
		return array( $field_name . "_columns" );
	}

	function display_configuration( $field_name, $field_label, $field_type, $data ) {
		$config = array();
		$config["label"] = form_label($field_label)
			. BR . anchor("http://brandnewbox.co.uk/support/details/datagrab_and_matrix_fields", "(&#946;eta notes)", 'class="help"');

		$this->EE->db->select( "col_id, col_label" );
		$query = $this->EE->db->get( "exp_matrix_cols" );
		$matrix_columns = array();
		foreach( $query->result_array() as $row ) {
			$matrix_columns[ $row["col_id"] ] = $row["col_label"];
		}

		$cells = form_hidden( $field_name, "1" );
		foreach( $data["field_settings"][ $field_name ][ "col_ids"] as $col_id ) {
			if( isset($data["default_settings"]["cf"][ $field_name . "_columns" ]) ) {
				$default_cells = $data["default_settings"]["cf"][ $field_name . "_columns" ];
			} else {
				$default_cells = array();
			}
			$cells .= "<p>" . 
				$matrix_columns[ $col_id ] . NBS . ":" . NBS . 
				form_dropdown( 
					$field_name . "_columns[" . $col_id . "]", 
					$data["data_fields"],
					isset($default_cells[$col_id]) ? $default_cells[$col_id] : ''
				) . 
				"</p>";
		}

		$config["value"] = $cells;
		return $config;
	}

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
	}
	
	function post_process_entry( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		/* Matrix does not work with the Channel Entries API so 
		   we'll have to 'bodge' it here. */
		
		$DG->_get_channel_fields_settings( $field_id );
		$fs = $this->EE->api_channel_fields->settings[ $field_id ]["field_settings"];
		$field_settings = (unserialize(base64_decode($fs)));

		// Get matrix column details (eg, the column type - playa, text, etc)
		$this->EE->db->select( "*" );
		$this->EE->db->where_in( "col_id", $field_settings["col_ids"] );
		$query = $this->EE->db->get( "exp_matrix_cols" );
		$columns = array();
		foreach( $query->result_array() as $row ) {
			$columns[ $row["col_id"] ] = $row;
		}
		
		// Delete old matrix rows?

		$used_fields = 0;
		foreach( $DG->settings["cf"][ $field . "_columns" ] as $col_id => $i ) {
			if( $i != "" ) {
				$used_fields++;
			}
		}

		if( !in_array( $update, $DG->entries ) ) {
			// todo: deletes fields even if not set
			// print "<p>First update to entry_id " . $update . " this import</p>";
			if( $used_fields > 0 ) {
				$matrix = array(
					"site_id" => $DG->channel_defaults["site_id"],
					"entry_id" => $update,
					"field_id" => $field_id
				);
				$this->EE->db->delete('exp_matrix_data', $matrix);
			}
		}

		// Loop through items, building matrix array
		// Note: we build the array 1 column at a time
		$matrix = array();
		$col_num = 0;
		foreach( $DG->settings["cf"][ $field . "_columns" ] as $col_id => $i ) {

			$row_num = 0;
			
			// Check this field can be a sub-loop
			if( $DG->datatype->initialise_sub_item( 
				$item, $i, $DG->settings, $field ) ) {

				// Start a new row:
				if( $row_num == 0 && $col_num == 0 ) {
					$matrix[ $row_num ] = array();
				}

				// Loop over sub items
				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $i, $DG->settings, $field ) ) {
						
						// Handle where subitem = date or playa field
						switch( $columns[ $col_id ]["col_type"] ) {
							case "date": {
								$matrix[$row_num]["col_id_".$col_id] = $DG->_parse_date( $subitem );
								break;
							}
							default: {
								$matrix[$row_num]["col_id_".$col_id] = $subitem;
							}
						}
						
						$row_num++;
				}
				
			}

			$col_num++;

		}

		// print_r( $matrix );
		$count = 0;
		foreach( $matrix as $mat ) {
			if( count( $mat ) > 0 ) {
				$mat["site_id"] = $DG->channel_defaults["site_id"];
				$mat["entry_id"] = $update;
				$mat["field_id"] = $field_id;
				$mat["row_order"] = ++$count;
				$this->EE->db->insert('exp_matrix_data', $mat);
			}
		}
		
		
	}

}

?>