<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once PATH_THIRD.'zoo_visitor/config.php';

class Zoo_visitor_ft extends EE_Fieldtype {

	var $info = array(
		'name'		=> ZOO_VISITOR_NAME,
		'version'	=> ZOO_VISITOR_VER
	);
	var $class_name = ZOO_VISITOR_CLASS;
	
	// Parser Flag (preparse pairs?)
	var $has_array_data = TRUE;

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Zoo_visitor_ft()
	{
		parent::EE_Fieldtype();
				
		$this->EE->load->add_package_path(PATH_THIRD .'zoo_visitor/');
		$this->EE->load->library('zoo_visitor_lib');
		$this->EE->load->helper('zoo_visitor');
		$this->EE->lang->loadfile('zoo_visitor');
		$this->zoo_settings = get_zoo_settings($this->EE);
			
		if(REQ == 'CP'){
			$this->EE->cp->add_to_head('<link rel="stylesheet" href="'.$this->EE->config->item('theme_folder_url') . '/third_party/zoo_visitor/css/zoo_visitor.css" type="text/css" media="screen" /> ');
		}	
	}
	
	
	// --------------------------------------------------------------------
	
	function display_field($data)
	{

		if($this->EE->session->userdata['can_admin_members'] == 'y' )
		{
			$entry_id = isset($_GET['entry_id']) ? $_GET['entry_id'] : 0;//isset($this->EE->uri->config->_global_vars['gv_get_entry_id']) ? $this->EE->uri->config->_global_vars['gv_get_entry_id'] : 0;
			$channel_id = $_GET['channel_id'];//$this->EE->uri->config->_global_vars['gv_get_channel_id'];
	
			if(!isset($this->zoo_settings['member_channel_id']) || $this->zoo_settings['member_channel_id'] != $channel_id){
				return "This channel is not linked with member profiles";
			}else{
		
				$member = $this->EE->zoo_visitor_lib->get_member_id($entry_id);
			
				if($member == FALSE)
				{
					return $this->add_form();
				}
				else
				{
					return $this->edit_form($member);
				}
			
			}
		}
		else
		{
			return '<div class="notice">'.lang('zoo_visitor_error_can_admin_members').'</div>';
		}
	}
	
	function add_form(){
		//use when publishing new entry or when member hasn't been linked with entry
		//group, username, screen_name, email, password, confirm password
		//OR dropdown with members
		//validate to see if the user hasn't been linked yet.
		//current member_id
		$member_id = form_input(array(
			'name'		=> "EE_member_id",
			'id'		=> "EE_member_id",
			'value'		=> '',
			'dir'		=> $this->settings['field_text_direction'],
			'style'		=> 'display:none;'
		));
		
		//get current email
		$email = '<label>Email:</label>'.form_input(array(
			'name'		=> "EE_email",
			'id'		=> "EE_email",
			'value'		=> isset($_POST['EE_email']) ? $_POST['EE_email'] : '',
			'dir'		=> $this->settings['field_text_direction']
		));
		
		//get current screen_name
		$screen_name_style = ($this->zoo_settings['use_screen_name'] == "no") ? 'display:none;' : '';
		$screen_name = '<div style='.$screen_name_style.'><label>Screen name:</label>'.form_input(array(
			'name'		=> "EE_screen_name",
			'id'		=> "EE_screen_name",
			'value'		=> isset($_POST['EE_screen_name']) ? $_POST['EE_screen_name'] : '',
			'dir'		=> $this->settings['field_text_direction']
		)).'</div>';

		//get current username
		$username_style = ($this->zoo_settings['email_is_username'] == 'yes') ? 'display:none;' : '';
		$username = '<div style='.$username_style.'><label>Username:</label>'.form_input(array(
			'name'		=> "EE_username",
			'id'		=> "EE_username",
			'value'		=> isset($_POST['EE_username']) ? $_POST['EE_username'] : '',
			'dir'		=> $this->settings['field_text_direction']
		)).'</div>';
		
		//get current member group
		$unlocked_groups = $this->EE->zoo_visitor_lib->get_member_groups();
		if ($this->EE->cp->allowed_group('can_admin_mbr_groups') && count($unlocked_groups) > 0){
			$groupSel = isset($_POST['EE_group_id']) ? $_POST['EE_group_id'] : '4';
			$group = '<label>Member group:</label>'.form_dropdown('EE_group_id',  $this->EE->zoo_visitor_lib->get_member_groups(), $groupSel, 'style="width:100%;"');
	
		}else{
			$group = '<div style="display:none;"><label>Member group:</label>'.form_input(array(
				'name'		=> "EE_group_id",
				'id'		=> "EE_group_id",
				'value'		=> '4',
				'dir'		=> $this->settings['field_text_direction']
			)).'</div>';
		}
		//new password
		$password = '<label>Password:</label>'.form_password(array(
			'name'		=> "EE_password",
			'id'		=> "EE_password",
			'value'		=> isset($_POST['EE_password']) ? $_POST['EE_password'] : '',
			'dir'		=> $this->settings['field_text_direction']
		));

		//confirm new password
		$confirm_password = '<label>Confirm password:</label>'.form_password(array(
			'name'		=> "EE_new_password_confirm",
			'id'		=> "EE_new_password_confirm",
			'value'		=> isset($_POST['EE_new_password_confirm']) ? $_POST['EE_new_password_confirm'] : '',
			'dir'		=> $this->settings['field_text_direction']
		));
		
		
		if( ( isset($this->zoo_settings['hide_link_to_existing_member']) && $this->zoo_settings['hide_link_to_existing_member'] != 'yes' ) || $this->EE->session->userdata('group_id') == '1' )
		{
			$anon_mem = (isset($this->zoo_settings['anonymous_member_id'])) ? $this->zoo_settings['anonymous_member_id'] : 0;
		
			//Get all member_id's 
			$sql = "SELECT mem.member_id, mem.screen_name, mem.email FROM exp_members mem WHERE mem.member_id != '".$anon_mem."'";
			$q_mem = $this->EE->db->query($sql);
			$all_members = array();
		
			foreach($q_mem->result_array() as $member)
			{
				$all_members[$member['member_id']] = $member['screen_name'].' ('.$member['email'].')';
			}

			//get members who already have a Visitor profile
			$sql = "SELECT ct.author_id FROM exp_channel_titles ct WHERE ct.channel_id = '".$this->zoo_settings['member_channel_id']."'";
			$q_visitor = $this->EE->db->query($sql);
			$all_visitors = array();
		
			foreach($q_visitor->result_array() as $entry)
			{
				$all_visitors[$entry['author_id']] = $entry;
			}
		
			$memberData[''] = 'Select a member';
			$memberData += array_diff_key($all_members, $all_visitors);
		
			$members = (count($memberData) > 1) ? '<b>OR Link an existing member:</b><br/><br/>'.form_dropdown('EE_existing_member_id', $memberData, '').'</b>' : '';
		
		}
		else
		{
			$members = '';
		}	
		
		
		$hide_title = '<script>$(document).ready(function() { $("#author").parent().parent().parent().hide();$("#title").parent().parent().parent().children("#sub_hold_field_title").hide();$("#title").parent().parent().parent().children("label").children("span").children(".required").html("");$("#url_title").parent().parent().parent().hide(); });</script>';
		
		$member_id_input = form_input($this->field_name, '', 'id="'.$this->field_name.'" style="display: none;"');
	
		return $member_id_input.'<div class="zoo_visitor_ft_left"><b>Create a new member:</b><br/><br/>'.$group.$member_id.$username.$email.$screen_name.$password.$confirm_password.'</div><div class="zoo_visitor_ft_right">'.$members.'</div><div style="clear:left;"></div>'.$hide_title ;
	
	
	
		
	}
	
	function edit_form($member){
		
		$own_account = ($this->EE->session->userdata('member_id') == $member->member_id) ? '<br/><b>Warning: this is your own account</b>' : '';
		
		//current member_id
		$member_id = form_input(array(
			'name'		=> "EE_member_id",
			'id'		=> "EE_member_id",
			'value'		=> $member->member_id,
			'dir'		=> $this->settings['field_text_direction'],
			'style'		=> 'display:none;'
		));
		
		//get current email
		$email = '<label>Email:</label>'.form_input(array(
			'name'		=> "EE_email",
			'id'		=> "EE_email",
			'value'		=> $member->email,
			'dir'		=> $this->settings['field_text_direction']
		)).form_input(array(
			'name'		=> "EE_current_email",
			'id'		=> "EE_current_email",
			'value'		=> $member->email,
			'dir'		=> $this->settings['field_text_direction'],
			'style'		=> 'display:none;'
		));
		
		//get current screen_name
		$screen_name_style = ($this->zoo_settings['use_screen_name'] == "no") ? 'display:none;' : '';
		$screen_name = '<div style='.$screen_name_style.'><label>Screen name:</label>'.form_input(array(
			'name'		=> "EE_screen_name",
			'id'		=> "EE_screen_name",
			'value'		=> $member->screen_name,
			'dir'		=> $this->settings['field_text_direction']
		)).form_input(array(
			'name'		=> "EE_current_screen_name",
			'id'		=> "EE_current_screen_name",
			'value'		=> $member->screen_name,
			'dir'		=> $this->settings['field_text_direction'],
			'style'		=> 'display:none;'
		)).'</div>';

		//get current username
		$username_style = ($this->zoo_settings['email_is_username'] == 'yes') ? 'display:none;' : '';
		$username = '<div style='.$username_style.'><label>Username:</label>'.form_input(array(
			'name'		=> "EE_username",
			'id'		=> "EE_username",
			'value'		=> $member->username,
			'dir'		=> $this->settings['field_text_direction']
		)).form_input(array(
			'name'		=> "EE_current_username",
			'id'		=> "EE_current_username",
			'value'		=> $member->username,
			'dir'		=> $this->settings['field_text_direction'],
			'style'		=> 'display:none;'
		)).'</div>';
		
		//get current member group
		if ($this->EE->cp->allowed_group('can_admin_mbr_groups')){
			$groups = $this->EE->zoo_visitor_lib->get_member_groups();
			if(!empty($groups))
			{
				$group = '<label>Member group:</label>'.form_dropdown('EE_group_id', $this->EE->zoo_visitor_lib->get_member_groups(), $member->group_id);
			}
			else
			{
				$group = '<div style="display:none;"><label>Member group:</label>'.form_input(array(
					'name'		=> "EE_group_id",
					'id'		=> "EE_group_id",
					'value'		=> $member->group_id,
					'dir'		=> $this->settings['field_text_direction']
				))."</div>";
			}
		
		}else{
			$group = '<div style="display:none;"><label>Member group:</label>'.form_input(array(
				'name'		=> "EE_group_id",
				'id'		=> "EE_group_id",
				'value'		=> $member->group_id,
				'dir'		=> $this->settings['field_text_direction']
			))."</div>";
		}
		//current password
		
		if($this->EE->session->userdata('group_id') == 1 || $this->EE->cp->allowed_group('can_admin_members')){
			$current_password = '';
		}
		else
		{
			$current_password = '<label>Current member password:</label>'.form_password(array(
				'name'		=> "EE_current_password",
				'id'		=> "EE_current_password",
				'value'		=> '',
				'dir'		=> $this->settings['field_text_direction']
			));
		}
		//new password
		$new_password = '<label>New password:</label>'.form_password(array(
			'name'		=> "EE_new_password",
			'id'		=> "EE_new_password",
			'value'		=> '',
			'dir'		=> $this->settings['field_text_direction']
		));
		//confirm new password
		$confirm_new_password = '<label>Confirm new password:</label>'.form_password(array(
			'name'		=> "EE_new_password_confirm",
			'id'		=> "EE_new_password_confirm",
			'value'		=> '',
			'dir'		=> $this->settings['field_text_direction']
		));
		
		// ====================================
		// = Administrative Member functions  =
		// ====================================
		$this->EE->lang->loadfile('myaccount');
		
		$email_member = '';
		if($member->member_id != $this->EE->session->userdata('member_id')){
			$email_member = '<a href="'.BASE.AMP.'C=tools_communicate'.AMP.'email_member='.$member->member_id.'" class="email">'.lang('member_email').' &raquo;</a>';
		}
	
		$login_as_member = '';
		if($this->EE->session->userdata('group_id') == 1 && $member->member_id != $this->EE->session->userdata('member_id')){
			$login_as_member = '<a href="'.BASE.AMP.'C=members'.AMP.'M=login_as_member'.AMP.'mid='.$member->member_id.'" class="login">'.lang('login_as_member').' &raquo;</a>';
		}

		$resend_activation = '';
		if ($member->member_id != $this->EE->session->userdata('member_id') &&	$this->EE->config->item('req_mbr_activation') == 'email' && $this->EE->cp->allowed_group('can_admin_members')){
			$resend_activation = '';
		}
		
		$delete_member = '';
		if( $this->EE->cp->allowed_group('can_delete_members') AND $member->member_id != $this->EE->session->userdata('member_id') ){
			if ( $member->group_id == '1' AND $this->EE->session->userdata('group_id') != '1' )
			{
				
			}
			else
			{
				$delete_member = '<a href="'.BASE.AMP.'C=members'.AMP.'M=member_delete_confirm'.AMP.'mid='.$member->member_id.'" class="delete">'.lang('delete').' &raquo;</a>';
			}
			//$delete_member = '<a href="'.BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=zoo_visitor'.AMP.'method=delete'.AMP.'mid='.$member->member_id.'" class="delete">'.lang('delete').' &raquo;</a>';
		
		
		}
		
		
		$join_data = '<br/><b>'.lang('join_date').'</b>: '.$this->EE->localize->set_human_time($member->join_date).'<br/><br/>';
		$last_visit = ($member->last_visit == 0 OR $member->last_visit == '') ? '--' : $this->EE->localize->set_human_time($member->last_visit);
		$last_visit = '<b>'.lang('last_visit').'</b>: '.$last_visit.'<br/>';
		
		if ($this->EE->cp->allowed_group('can_admin_members')){
			$member_functionality = $email_member.$login_as_member.$delete_member;
			$member_functionality_title = ($member_functionality != '') ? '<h3>'.lang('administrative_options').':</h3>' : '';
			
		}
		
		$hide_title = '<script>$(document).ready(function() { ;$("#title").parent().parent().parent().children("#sub_hold_field_title").hide();$("#title").parent().parent().parent().children("label").children("span").children(".required").html("");  $("#author").parent().parent().parent().hide();  $("#url_title").parent().parent().parent().hide(); });</script>';
		
		
		return '<div class="zoo_visitor_ft_left"><h3>'.lang('personal_settings').':</h3>'.$member_id.$group.$email.$screen_name.$username.$current_password.$new_password.$confirm_new_password.$own_account.$hide_title.'</div> <div class="zoo_visitor_ft_right">'.$member_functionality_title.$member_functionality.$join_data.$last_visit.'</div>';
	
	}
	
	// --------------------------------------------------------------------
	
	function validate($data)
	{
		
		if(REQ != 'CP'){
			//request comes from frontend SafeCracker, validation is done in extension
			return TRUE;
		}else{
			//TODO, custom member fields, for example campaigner triggers
			//edit POST title with email
		
			$this->prepare_post();
			
			//member has been linked
			if(isset($_POST['EE_member_id']) && $_POST['EE_member_id'] != 0 && $_POST['EE_member_id'] != ''){

				if($_POST['EE_current_email'] != $_POST['EE_email']){
					$result = $this->EE->zoo_visitor_cp->update_email(FALSE);
					if($result['result'] == 'failed'){ return $this->showError($result); }
				}
			//&& $this->zoo_settings['email_is_username'] == "no"
				if( ( $_POST['EE_current_username'] != $_POST['EE_username'] ) || $_POST['EE_new_password'] != '' || ( $_POST['EE_current_screen_name'] != $_POST['EE_screen_name'] && $this->zoo_settings['use_screen_name'] == "yes") ){
					
					$result = $this->EE->zoo_visitor_cp->update_username_password(FALSE);

					if($result['result'] == 'failed'){ return $this->showError($result); }
				}
			
			}else{
				//member has not been linked yet
				if(isset($_POST['EE_existing_member_id']) && $_POST['EE_existing_member_id'] != 0 && $_POST['EE_existing_member_id'] != ''){
					//existing member has been selected, valid
					//set member as entry author id 
					//$_POST['author'] = $_POST['EE_existing_member_id']; 
					
					

					return TRUE;
				}else{
			
					$result = $this->EE->zoo_visitor_cp->validate_member($this->zoo_settings['use_screen_name']);
					if($result['result'] == 'failed'){ return $this->showError($result); }
				}
			}
		
			return TRUE;
		}
	}
	
	function post_save($data){

		//sync back to member, for certain add-ons
		$this->EE->zoo_visitor_cp->sync_back_to_member($this->settings['entry_id']);
		
		if(REQ != 'CP'){
			//request comes from frontend SafeCracker, validation is done in extension
			return TRUE;
		}else{
				
			$this->prepare_post();
			
			$this->EE->zoo_visitor_cp->entry_id = $this->settings['entry_id'];
				
			//member has been already been linked, update the details
			if(isset($_POST['EE_member_id']) && $_POST['EE_member_id'] != 0 && $_POST['EE_member_id'] != ''){
			
				$ft_action = "update";
				
				//set member_id, needed as field value
				$member_id = $_POST['EE_member_id'];
				//save email if it has been saved
				if($_POST['EE_current_email'] != $_POST['EE_email']){
					$this->EE->zoo_visitor_cp->update_email(TRUE);
				}
				//save username or password or screen_name
				//&& $this->zoo_settings['email_is_username'] == "no" 
				if( ( $_POST['EE_current_username'] != $_POST['EE_username']) || $_POST['EE_new_password'] != '' || ( $_POST['EE_current_screen_name'] != $_POST['EE_screen_name'] && $this->zoo_settings['use_screen_name'] == "yes") ){
					$this->EE->zoo_visitor_cp->update_username_password(TRUE);
				}
			
				if(isset($_POST['group_id']) && $_POST['group_id'] != 0 && $_POST['group_id'] != ''){
					$this->EE->zoo_visitor_cp->member_group_update();
				}
				
			}else{
 				
				$ft_action = "register";
				
	 			if(isset($_POST['EE_existing_member_id']) && $_POST['EE_existing_member_id'] != 0 && $_POST['EE_existing_member_id'] != ''){
	 				$member_id = $_POST['EE_existing_member_id'];
	 			}else{
	 				//register member
					$member_id = $this->EE->zoo_visitor_cp->register_member();
				}	
				
				//set member as author of this entry
				$this->EE->db->query("UPDATE exp_channel_titles SET author_id = '".$member_id."' WHERE channel_id='".$this->zoo_settings['member_channel_id']."' AND entry_id = '".$this->settings['entry_id'] ."'");
				//sync the membergroup status
				$this->EE->zoo_visitor_cp->sync_member_status($member_id);
				
			}

			//update zoo_visitor field to contain the member_id
			$this->EE->db->query("UPDATE exp_channel_data SET ".$this->field_name." = '".$member_id."' WHERE entry_id = '".$this->settings['entry_id'] ."'");	
		
			//sync the screen_name based on the provided override fields
			if($this->zoo_settings['use_screen_name'] == "no" && $this->zoo_settings['screen_name_override'] != ''){
				$this->EE->zoo_visitor_lib->update_screen_name($member_id);
			}
			
			$this->EE->zoo_visitor_lib->update_entry_title($this->settings['entry_id']);
		
			// ========================
			// = ZOO VISITOR CP HOOKS =
			// ========================
			if($ft_action == "update")
			{
				// -------------------------------------------
				// 'zoo_visitor_cp_update' hook.
				//  - Additional processing when a member is updated through the Control Panel entry 
				//
				$hook_data = $_POST;
				$edata = $this->EE->extensions->call('zoo_visitor_cp_update_end', $hook_data, $member_id);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
			
			if($ft_action == "register")
			{
				// -------------------------------------------
				// 'zoo_visitor_cp_register' hook.
				//  - Additional processing when a member is created through the Control Panel entry publish
				//
				$hook_data = $_POST;
				$edata = $this->EE->extensions->call('zoo_visitor_cp_register_end', $hook_data, $member_id);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
			
		
		}
		
	}
	
	// --------------------------------------------------------------------
	
	function showError($result){
				
			$this->EE->load->language('member');
			$this->EE->load->language('myaccount');
			$return = '';
			foreach($result['errors'] as $error){
		
				$return .= $this->EE->lang->line($error).'<br/>';
			}
			
			return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	function prepare_post(){

		//get existing member data to set as entry title
		if(isset($_POST['EE_existing_member_id']) && $_POST['EE_existing_member_id'] != 0 && $_POST['EE_existing_member_id'] != ''){
			
		}else{
		
			if($this->zoo_settings['email_is_username'] == 'yes'){
				$_POST['EE_username'] 	= (isset($_POST['EE_email'])) ? $_POST['EE_email'] : "";
			}
			if($this->zoo_settings['use_screen_name'] == "no"){
				$_POST['EE_screen_name'] = (isset($_POST['EE_username'])) ? $_POST['EE_username'] : "";
			}
			
			$_POST['email'] 			= isset($_POST['EE_email']) ? $_POST['EE_email'] : '';
			$_POST['username'] 			= isset($_POST['EE_username']) ? $_POST['EE_username'] : '';
			$_POST['current_username'] 	= isset($_POST['EE_current_username']) ? $_POST['EE_current_username'] : '';
			$_POST['password'] 			= isset($_POST['EE_new_password']) ? $_POST['EE_new_password'] : '';
			$_POST['password_confirm']	= isset($_POST['EE_new_password_confirm']) ? $_POST['EE_new_password_confirm'] : '';
			$_POST['current_password'] 	= isset($_POST['EE_current_password']) ? $_POST['EE_current_password'] : '';
			$_POST['screen_name'] 		= isset($_POST['EE_screen_name']) ? $_POST['EE_screen_name'] : '';
			$_POST['group_id']	 		= isset($_POST['EE_group_id']) ? $_POST['EE_group_id'] : '';
			
			//member passwords for publish, not update
			$_POST['password'] 			= isset($_POST['EE_password']) ? $_POST['EE_password'] : $_POST['password'];
			$_POST['password_confirm']	= isset($_POST['EE_password_confirm']) ? $_POST['EE_password_confirm'] : $_POST['password_confirm'];
			
			$this->EE->zoo_visitor_cp->id = isset($_POST['EE_member_id']) ? $_POST['EE_member_id'] : '';
		
		}
			
		$_POST['title'] = "temp";//$_POST['email'].' // '.$_POST['username']; //$this->prepare_title();
	
	}		
}
?>