<?php

/**
 * DataGrab Module Class
 *
 * DataGrab Module class used in front end templates
 * 
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Ajw_datagrab {

	var $return_data    = ''; 

	function Ajw_datagrab()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
		
		// Load datagrab model
		$this->EE->load->model('datagrab_model', 'datagrab');
	}

	/**
	 * Run an import via an action
	 *
	 * @return void
	 * @author Andrew Weaver
	 */
	function run_action() {
		
		if( $this->EE->input->get("id") != "" ) {
			$id = $this->EE->input->get("id");
		} 

		$this->EE->load->helper('url');
		$this->EE->load->library('javascript'); 
		$this->EE->load->model('template_model'); 

		// Fetch import settings
		if ( $id != "" ) {
			$this->EE->db->where('id', $id );
			$query = $this->EE->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			$this->settings = unserialize($row["settings"]);
		}

		// Initialise
		$this->EE->datagrab->initialise_types();

		// Check for modifiers
		if( $this->EE->input->get('filename') !== FALSE ) {
			$this->settings["datatype"]["filename"] = $this->EE->input->get('filename');
		}
		if( $this->EE->input->get('skip') !== FALSE ) {
			$this->settings["datatype"]["skip"] = $this->EE->input->get('skip');
		}
		if( $this->EE->input->get('limit') !== FALSE ) {
			$this->settings["import"]["limit"] = $this->EE->input->get('limit');
		}

		// Do import
		$this->return_data .= $this->EE->datagrab->do_import( 
			$this->EE->datagrab->datatypes[ $this->settings["import"]["type"] ], 
			$this->settings 
			);

		$this->return_data .= "<p>Import has finished.</p>";
		
		print $this->return_data;
		exit;
	}

	/**
	 * Run an import from a front end template
	 *
	 * @return void
	 * @author Andrew Weaver
	 */
	function run_saved_import() {
		
		$id = $this->EE->TMPL->fetch_param('id');

		$this->EE->load->helper('url');
		$this->EE->load->library('javascript'); 
		$this->EE->load->model('template_model'); 

		// Fetch import settings
		if ( $id != "" ) {
			$this->EE->db->where('id', $id );
			$query = $this->EE->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			$this->settings = unserialize($row["settings"]);
		}

		// Initialise
		$this->EE->datagrab->initialise_types();

		// Check for template modifiers
		if( $this->EE->TMPL->fetch_param('filename') !== FALSE ) {
			$this->settings["datatype"]["filename"] = $this->EE->TMPL->fetch_param('filename');
		}
		if( $this->EE->TMPL->fetch_param('skip') !== FALSE ) {
			$this->settings["datatype"]["skip"] = $this->EE->TMPL->fetch_param('skip');
		}
		if( $this->EE->TMPL->fetch_param('limit') !== FALSE ) {
			$this->settings["import"]["limit"] = $this->EE->TMPL->fetch_param('limit');
		}
		

		// Do import
		$this->return_data .= $this->EE->datagrab->do_import( 
			$this->EE->datagrab->datatypes[ $this->settings["import"]["type"] ], 
			$this->settings 
			);

		$this->return_data .= "<p>Import has finished.</p>";
		
		return $this->return_data;
		// exit;
	}

}

/* End of file mod.ajw_datagrab.php */