<?php 
@session_start();
if (!defined('BASEPATH')) exit('Invalid file request');
require_once PATH_THIRD.'zoo_visitor/config.php';
/**
 * Zoo visitor Extension Class
 *
 * @package   Zoo visitor
 * @author    ExpressionEngine Zoo <info@eezoo.com>
 * @copyright Copyright (c) 2011 ExpressionEngine Zoo (http://eezoo.com)
 */
class Zoo_visitor_ext 
{
	var $base;
	var $form_base;
	var $name = ZOO_VISITOR_NAME;
	var $class_name = ZOO_VISITOR_CLASS;
	var $settings_exist = 'n';
	var $docs_url = ZOO_VISITOR_DOCS;
	var $version = ZOO_VISITOR_VER;
	
	var $settings_default = array();
	var $field_errors = array();
	/**
	 * Extension Constructor
	 */
	function Zoo_visitor_ext()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->add_package_path(PATH_THIRD .'zoo_visitor/');
		$this->EE->load->library('zoo_visitor_lib');
		$this->EE->load->library('zoo_visitor_cp');
		$this->EE->load->helper('zoo_visitor');
		$this->zoo_settings = get_zoo_settings($this->EE);
	}

	// --------------------------------------------------------------------
	
	function hook_member_member_register_start(){
		//used in front
		//set username, screenname based on preferences
	}
	
	function hook_member_member_register($data, $member_id){
		//used in front
		//insert new entry
		return $data;	
	}
	
	function hook_member_register_validate_members($member_id){
		//redirect to page
		///////////////////
		if ($this->EE->db->table_exists('zoo_visitor_activation_membergroup'))
		{
			$this->EE->db->select('group_id');
			$query = $this->EE->db->get_where('zoo_visitor_activation_membergroup', array('member_id' => $member_id));
			
			if($query->num_rows() > 0 )
			{
				foreach ($query->result() as $row)
				{
					$this->EE->db->where('member_id', $member_id);
					$this->EE->db->update('members', array('group_id' => $row->group_id));
					$this->EE->db->delete('zoo_visitor_activation_membergroup', array('member_id' => $member_id));
				}
			}
		}
		
		//update the membergroup status
		$this->EE->zoo_visitor_cp->sync_member_status($member_id);
	
	}
	
	function hook_cp_js_end(){
		
		$incoming = '';
		if($this->EE->extensions->last_call == TRUE && $this->EE->extensions->last_call != ''){
			$incoming = $this->EE->extensions->last_call;
		}

	//	if(isset($_GET['M']) && $_GET['M'] == 'member_confirm' && $_GET['C'] == 'members' ){
		//check if this is the delete page?
		
		//if the only entry is the profile entry
		//set "heir" value to anonymous user
			$js = '';
			
			if(isset($this->zoo_settings['anonymous_member_id']))
			{
				$js .= '$(document).ready(function() {
				$(\'select[name="heir"]\').append(\'<option value="'.$this->zoo_settings['anonymous_member_id'].'" selected="selected">ZOO VISITOR</option>\');
				}); ';
				
				$js .= 'var tableRow = $("td").filter(function() { return $(this).text() == "zoo_visitor_guest";}).closest("tr"); tableRow.hide();';
			}

			if(isset($_SESSION['zoo_visitor_has_other_entries']) && $_SESSION['zoo_visitor_has_other_entries'] == "no")
			{	
				$js .= '$(\'label[for="heir"]\').hide();';
				$js .= '$(\'select[name="heir"]\').hide();';
			}
			return $js.$incoming;
	//	}
		
	}
	function hook_sessions_end(&$SESS){
		
		//set the entry id of the member profile entry available as a global var
		if(isset($SESS->sdata['member_id'])){
			$this->EE->config->_global_vars = array_merge($this->EE->config->_global_vars, array('zoo_member_id' => $SESS->sdata['member_id']));
			$zoo_visitor_id = $this->EE->zoo_visitor_lib->get_visitor_id($SESS->sdata['member_id']);
			$this->EE->config->_global_vars['zoo_visitor_id'] = $zoo_visitor_id;
			
			// =======================================
			// = GET THE MEMBER DATA AS GLOBAL VARS? =
			// =======================================
			if($zoo_visitor_id)
			{
				$fieldq = $this->EE->db->query('SELECT ch.field_group, cf.field_id, cf.field_name FROM exp_channels ch, exp_channel_fields cf WHERE ch.channel_id = "'.$this->zoo_settings['member_channel_id'].'" AND cf.group_id = ch.field_group');

				if(isset($fieldq) && $fieldq->num_rows() > 0)
				{

					$field_ids = array();
					$field_names = array();
					foreach($fieldq->result_array() as $row)
					{
						array_push($field_ids, 'field_id_'.$row['field_id']);
						$field_names[$row['field_id']] = $row['field_name'];
					}
					
					$fields = implode(',',$field_ids);
					$dataq = $this->EE->db->query('SELECT ct.url_title, ct.expiration_date, '.$fields.' FROM exp_channel_data cd, exp_channel_titles ct WHERE cd.entry_id = "'.$zoo_visitor_id.'" AND ct.entry_id  = "'.$zoo_visitor_id.'"');
					
					if(isset($dataq) && $dataq->num_rows() > 0)
					{
						$values = $dataq->row_array();
					
						$visitor_data = array();
						foreach($field_names as $field_id => $field_name)
						{
							$visitor_data["visitor:global:".$field_name] = $values['field_id_'.$field_id];
						}
						
						$visitor_data["visitor:global:url_title"] = $values['url_title'];
						$visitor_data["visitor:global:expiration_date"] = $values['expiration_date'];
						
						$this->EE->config->_global_vars = array_merge($this->EE->config->_global_vars, $visitor_data);
					}
				}
				
				$uploads = $this->EE->db->query('SELECT * FROM exp_upload_prefs');
				if(isset($uploads) && $uploads->num_rows() > 0)
				{
					$upload_data = array();
					foreach($uploads->result() as $row)
					{
						$upload_data["filedir_".$row->id] = $row->url;
					}
					$this->EE->config->_global_vars = array_merge($this->EE->config->_global_vars, $upload_data);
				}
			}
		}
		
		if(isset($this->zoo_settings['member_channel_name'])){
			$this->EE->config->_global_vars['zoo_visitor_channel_name'] = $this->zoo_settings['member_channel_name'];
		}
		// ============================================
		// = Delete member entry if member is deleted =
		// ============================================
		if(isset($_GET['M']) && $_GET['M'] == 'member_delete' && isset($_POST['delete'])){
		
			foreach($_POST['delete'] as $key => $member_id){	
				$entry_id = $this->EE->zoo_visitor_lib->get_visitor_id($member_id);
				if($this->EE->zoo_visitor_lib->get_visitor_id($member_id)){
					$this->EE->db->delete('channel_titles', array('entry_id' => $entry_id, 'site_id' => $this->EE->config->item('site_id'), 'channel_id' => $this->zoo_settings['member_channel_id']));
					$this->EE->db->delete('channel_data', array('entry_id' => $entry_id, 'site_id' => $this->EE->config->item('site_id'), 'channel_id' => $this->zoo_settings['member_channel_id']));
			
				}
			}	
		}
		
		// ============================================
		// = Delete member entry if member is deleted =
		// ============================================
		if(isset($_GET['M']) && $_GET['M'] == 'member_delete_confirm'){
		
			$query = $this->EE->db->query('SELECT * FROM exp_channel_titles WHERE author_id = "'.$_GET['mid'].'" AND channel_id != "'.$this->zoo_settings['member_channel_id'].'"');
			
			
			if($query->num_rows() == 0)
			{
				$_SESSION['zoo_visitor_has_other_entries'] = 'no';
			}
			else
			{
				$_SESSION['zoo_visitor_has_other_entries'] = 'yes';
			}
		}
		
		
		// ==============================================
		// = update member channel statusgroup & entry  =
		// ==============================================
		if(isset($_GET['M']) && $_GET['M'] == 'update_member_group' && isset($_POST['group_title']) && isset($_POST['group_id']) ){
			$this->EE->zoo_visitor_cp->update_membergroup_status($_POST['group_title'], $_POST['group_id']);
		}
		if(isset($_GET['M']) && $_GET['M'] == 'delete_member_group' && isset($_POST['new_group_id']) && isset($_POST['group_id']) ){
			$this->EE->zoo_visitor_cp->update_membergroup_status('', $_POST['group_id'], $_POST['new_group_id']);
		}	
		if(isset($_GET['M']) && $_GET['C'] == 'myaccount' && $_GET['M'] == 'member_preferences_update' && isset($_POST['group_id']) && isset($_POST['id']) ){
			$this->EE->zoo_visitor_cp->entry_id = $this->EE->zoo_visitor_lib->get_visitor_id($_POST['id']);
			$this->EE->zoo_visitor_cp->update_member_status($_POST['group_id']);
		}				
			
		return $SESS;
		
	}
	function hook_cp_members_member_delete_end(){
		//remove the entry
		//cannot be removed here, author_id has already been assigned to other user, done in sessions_end
	}
	
	
	function hook_cp_members_member_create($member_id, $data){
		//insert entry if it doesn't exists yet
		//is also called when adding a member in channel entry

		if(isset($_GET['C']) && $_GET['C'] != 'content_publish'){
			//construct entry title		
			$title = $data['email'];
			if($this->zoo_settings['email_is_username'] != 'yes'){
				$title .= ' - '.$data['username'];
			}	
			if($this->zoo_settings['use_screen_name'] != "no"){
				$title .= ' - '.$data['screen_name'];
			}
			
			$title_data = array();
			$title_data['site_id'] 		= $this->EE->config->item('site_id');
			$title_data['channel_id'] 	= $this->zoo_settings['member_channel_id'];
			$title_data['author_id'] 	= $member_id;
			$title_data['title']		= $title;
			$title_data['url_title'] 	= url_title($title);
			$title_data['status']		= 'open';
			$title_data['ip_address'] 	= $data['ip_address'];
			$title_data['entry_date']	= $this->EE->localize->now;
			$this->EE->db->insert('channel_titles', $title_data); 
			
			$entry_id = $this->EE->db->insert_id();
			
			$entry_data = array();
			$entry_data['site_id'] 		= $this->EE->config->item('site_id');
			$entry_data['channel_id'] 	= $this->zoo_settings['member_channel_id'];
			$entry_data['entry_id'] 	= $entry_id;		
			$this->EE->db->insert('channel_data', $entry_data); 
			
			$this->EE->zoo_visitor_cp->sync_member_status($member_id);
		}
		return $data;
		
	}
	
	function hook_cp_members_validate_members(){

		if(isset($_POST['toggle']))
		{
			foreach ($_POST['toggle'] as $key => $val)
			{
				//update the membergroup status
				$this->EE->zoo_visitor_cp->sync_member_status($val);
				
				if ($this->EE->db->table_exists('zoo_visitor_activation_membergroup'))
				{
					$this->EE->db->select('group_id');
					$query = $this->EE->db->get('zoo_visitor_activation_membergroup');
					
					if($query->num_rows() > 0 )
					{
						foreach ($query->result() as $row)
						{
							$this->EE->db->where('member_id', $val);
							$this->EE->db->update('members', array('group_id' => $row->group_id));
							$this->EE->db->delete('zoo_visitor_activation_membergroup', array('member_id' => $val));
						}
					}
				}
			}
		}
		
	}
		
	function hook_safecracker_submit_entry_start(&$obj){

		$this->EE->session->cache['zoo_visitor_field_errors'] = array();
		
		//zoo visitor action is set to register
		if(isset($_POST['zoo_visitor_action']) && ( $_POST['zoo_visitor_action'] == 'register' || $_POST['zoo_visitor_action'] == 'update')){
			
			$profile_fields = array("username", "screen_name", "password", "email", "new_password");
			
			//Check if there are any native member fields
			$native_member_fields_data = contains_native_member_fields();

			// ==============================================================
			// = is password required to update the regular channel fields? =
			// ==============================================================
			
			if(isset($_POST['zoo_visitor_require_password']) && $_POST['zoo_visitor_require_password'] == 'yes')
			{
				$current_password = (isset($_POST['current_password'])) ? $_POST['current_password'] : '';

				$member_id = $this->EE->zoo_visitor_lib->get_member_id($_POST['entry_id']);
				$member_id = ( $member_id ) ?  $member_id->member_id : $this->EE->session->userdata('member_id');

				$current_password_errors = $this->EE->zoo_visitor_lib->_validate_current_password($current_password, $member_id);

				if($current_password_errors != 'valid')
				{
					$obj->field_errors['current_password'] = $current_password_errors;
					$this->EE->session->cache['zoo_visitor_field_errors']['current_password'] = $current_password_errors;
				}
			}
			
			
			if(!isset($_POST['username']) && !isset($_POST['screen_name']) && !isset($_POST['password']) && !isset($_POST['email']) && !isset($_POST['new_password']) && $native_member_fields_data == FALSE){
				
				//This is no member profile update request
				
			}else{
				
				if(isset($_POST))
				{
					foreach($profile_fields as $name)
					{
						if(isset($_POST[$name]) && $_POST[$name] == "")
						{
							unset($_POST[$name]);
						}
					}
				}
				
				//this is a member profile update or registration
				$_POST['zoo_visitor_action'] = ($_POST['zoo_visitor_action'] == 'register') ? 'register' : 'update_profile';

				$title = '';
				//set the channel entry title, because this field is required
				if($this->zoo_settings['email_is_username'] == 'yes' && isset($_POST['email'])){
					$_POST['username'] 	= $_POST['email'];
				}
				if($this->zoo_settings['email_is_username'] == 'yes' && isset($_POST['username'])){
					$_POST['email'] = $_POST['username'];
				}	
				if($this->zoo_settings['use_screen_name'] == "no" && isset($_POST['username'])){
					
					$_POST['screen_name'] = $_POST['username'];
				}
				
				if($this->zoo_settings['password_confirmation'] == "no" && isset($_POST['password'])){
					$_POST['password_confirm'] = $_POST['password'];
				}
				if($this->zoo_settings['email_confirmation'] == "no" && isset($_POST['email'])){
					$_POST['email_confirm'] = $_POST['email'];
				}

				//the title will be synced when entry is submitted
				if(!isset($_POST['dynamic_title']))
				{
					$_POST['title'] = (isset($_POST['EE_title'])) ? $_POST['EE_title'] : 'no_title';
					$_POST['title'] = (isset($_POST['username'])) ? $_POST['username'] : $_POST['title'];
					$_POST['title'] = ($_POST['title'] == '') ? 'no_title' : $_POST['title'];
				}
				
				// =============================================
				// = CHECK FOR ERRORS AND PASS TO THE END HOOK =
				// =============================================
				
				// ==========================
				// = grab the update errors =
				// ==========================
				if($_POST['zoo_visitor_action'] == 'update_profile'){

					//Validate update 
					$validate_errors = $this->EE->zoo_visitor_lib->update(FALSE);
					if(count($validate_errors) > 0)
					{
						foreach($validate_errors as $key => $value)
						{
							if(count($value) > 0)
							{
								$obj->field_errors[$key] = implode('<br/>',$value);
								$this->EE->session->cache['zoo_visitor_field_errors'][$key] = implode('<br/>',$value);
							}

						}
					}
				}
				
				// ================================
				// = grab the registration errors =
				// ================================
				if($_POST['zoo_visitor_action'] == 'register'){
					
					$reg_result = $this->EE->zoo_visitor_lib->register_member($this, FALSE);
					
					if($reg_result[0] == "submission" && count($reg_result[1]) > 0)
					{
						foreach($reg_result[1] as $key => $value)
						{
							if(count($value) > 0)
							{
								$obj->field_errors[$key] = implode('<br/>',$value);
								$this->EE->session->cache['zoo_visitor_field_errors'][$key] = implode('<br/>',$value);
							}
						}
					}	
				
				}
				
			}
			
			// ==================================================================================
			// = extra merging of categories, can be used to split up categories for validation =
			// ==================================================================================
			if(isset($_POST['category_to_be_merged']) && !empty($_POST['category_to_be_merged']))
			{
				$category = (isset($_POST['category'])) ? $_POST['category'] : array();
				foreach ($_POST['category_to_be_merged'] as $key => $value) {
					$category = array_merge($category, $value);
				}
				$_POST['category'] = $category;
			}
			
			
		}

		return $obj;
	}
	
	function hook_safecracker_submit_entry_end(&$obj){

		// ===========================
		// = Get stored field errors =
		// ===========================
		
		$obj->field_errors = array_merge($obj->field_errors, $this->EE->session->cache['zoo_visitor_field_errors']);
		$this->EE->session->cache['zoo_visitor_field_errors'] = array();
		
		/** ----------------------------------------
		/**  Zoo visitor action is set to register
		/** ----------------------------------------*/
		//$this->EE->lang->loadfile('zoo_visitor');
		
		if(isset($_POST['zoo_visitor_action'])){
		
			// =========================
			// = create native profile =
			// =========================
			if($_POST['zoo_visitor_action'] == 'register'){
				
				//wrap errors if there is an error delimiter
				$obj->field_errors = prep_errors($this->EE, $obj->field_errors);
				
				if ( ( is_array($obj->errors) && count($obj->errors) > 0 ) || (is_array($obj->field_errors) && count($obj->field_errors) > 0) ){
					
					$this->EE->db->where('entry_id', $obj->entry('entry_id'));
					$this->EE->db->delete('channel_titles');
					$this->EE->db->where('entry_id', $obj->entry('entry_id'));
					$this->EE->db->delete('channel_data');
					
					//do nothing. let safecracker handle the error reporting
				
				}else{
					
					/** ----------------------------------------
					/** No Safecracker errors, register EE member
					/** ----------------------------------------*/
					$reg_result = $this->EE->zoo_visitor_lib->register_member($this);
					
					//EE member registration is complete, check result
					if(isset($reg_result['result']) && $reg_result['result'] == "registration_complete"){
						
						//registration successfull, set author_id in channel entry
						$this->EE->db->update('channel_titles', array('author_id' => $reg_result['member_data']['member_id']), 'entry_id = '.$obj->entry('entry_id'));
						
						//sync the screen_name based on the provided override fields
						if($this->zoo_settings['use_screen_name'] == "no" && $this->zoo_settings['screen_name_override'] != ''){
							$this->EE->zoo_visitor_lib->update_screen_name($reg_result['member_data']['member_id']);
						}
						
						if(!isset($_POST['dynamic_title']))
						{
							$this->EE->zoo_visitor_lib->update_entry_title($obj->entry('entry_id'));	
						}
					
						//set membergroup status
						$this->EE->zoo_visitor_cp->id = $reg_result['member_data']['member_id'];
						$this->EE->zoo_visitor_cp->entry_id = $obj->entry('entry_id');
						$this->EE->zoo_visitor_cp->update_member_status( $reg_result['member_data']['group_id'] );
						
						
					}else{
						
						/** ----------------------------------------
						/** EE member registration failed
						/** ----------------------------------------*/
						
						$this->EE->extensions->end_script = TRUE;
						
						//EE registration failed, remove member channel entry
						
						$this->EE->db->where('entry_id', $obj->entry('entry_id'));
						$this->EE->db->delete('channel_titles');
						$this->EE->db->where('entry_id', $obj->entry('entry_id'));
						$this->EE->db->delete('channel_data');
						
						$errors = array();
						foreach($reg_result[1] as $key => $value)
						{
							if(count($value) > 0)
							{
								$errors[$key] = implode('<br/>',$value);
							}
						}
						
						$this->EE->output->show_user_error($reg_result[0], $errors);
					
					}
				}
			}
			
			// =========================
			// = Native profile update =
			// =========================
			if($_POST['zoo_visitor_action'] == 'update_profile'){
				
				$obj->field_errors = prep_errors($this->EE, $obj->field_errors);
				
				//check for safecracker errors
				if ( ( is_array($obj->errors) && count($obj->errors) > 0 ) || (is_array($obj->field_errors) && count($obj->field_errors) > 0) ){
					
					//do nothing. let safecracker handle the error reporting
				
				}else{
					//do update
					$this->EE->zoo_visitor_lib->update(TRUE);
					
				}	
			}		
			
			if(!isset($_POST['dynamic_title']))
			{
				$this->EE->zoo_visitor_lib->update_entry_title($obj->entry('entry_id'));
			}
			
			//set membergroup status
			$this->EE->zoo_visitor_cp->sync_member_status($this->EE->session->userdata['member_id']);
			

			//sync the screen_name based on the provided override fields
			if($this->zoo_settings['use_screen_name'] == "no" && $this->zoo_settings['screen_name_override'] != ''){
				$this->EE->zoo_visitor_lib->update_screen_name();
			}
			
			
			// ===================
			// = Post processing =
			// ===================
			if(isset($reg_result['result']) && $reg_result['result'] == "registration_complete"){
				// -------------------------------------------
				// 'zoo_visitor_register' hook.
				//  - Additional processing when a member is created through the registration form tag
				//  Still present for backward compatibility with other add-ons using the old register hook
				//
				$edata = $this->EE->extensions->call('zoo_visitor_register', array_merge($reg_result['member_data'], $_POST), $reg_result['member_data']['member_id']);
				if ($this->EE->extensions->end_script === TRUE) return;

				// -------------------------------------------
				// 'zoo_visitor_register_end' hook.
				//  - Additional processing when a member is created through the registration form tag
				//
				$edata = $this->EE->extensions->call('zoo_visitor_register_end', array_merge($reg_result['member_data'], $_POST), $reg_result['member_data']['member_id']);
				if ($this->EE->extensions->end_script === TRUE) return;
				
				
					
				//check activation method, if none, auto-login
				if ($this->EE->config->item('req_mbr_activation') == 'none'){
					
					$this->autologin($reg_result['member_data'], $reg_result['member_data']['member_id']);
					
					//is redirect set?
					if($this->zoo_settings['redirect_after_activation'] == "yes"){
					
						$this->EE->extensions->end_script = TRUE;
						
						//sync the screen_name based on the provided override fields before the redirect
						if($this->zoo_settings['use_screen_name'] == "no" && $this->zoo_settings['screen_name_override'] != ''){
							$this->EE->zoo_visitor_lib->update_screen_name($reg_result['member_data']['member_id']);
						}
						
						//$this->redirect();	
					
					}
				}
				
				// ==============================================
				// = Send JSON RESPONSE WITH MEMBER ID INCLUDED =
				// ==============================================
				if($obj->json)
				{
					if (is_array($obj->errors))
					{
						//add the field name to custom_field_empty errors
						foreach ($obj->errors as $field_name => $error)
						{
							if ($error == $this->EE->lang->line('custom_field_empty'))
							{
								$obj->errors[$field_name] = $error.' '.$field_name;
							}
						}
					}
					
					return $obj->send_ajax_response(
							array(
								'success' => (empty($obj->errors) && empty($obj->field_errors)) ? 1 : 0,
								'errors' => (empty($obj->errors)) ? array() : $obj->errors,
								'field_errors' => (empty($obj->field_errors)) ? array() : $obj->field_errors,
								'entry_id' => $obj->entry('entry_id'),
								'member_id' => $reg_result['member_data']['member_id'],
								'url_title' => $obj->entry('url_title'),
								'channel_id' => $obj->entry('channel_id'),
							)
					);
				}
			}
			
			if($_POST['zoo_visitor_action'] == 'update_profile' || $_POST['zoo_visitor_action'] == 'update'){
				// -------------------------------------------
				// 'zoo_visitor_update_end' hook.
				//  - Additional processing when a member is update through the update form tag
				//
				$edata = $this->EE->extensions->call('zoo_visitor_update_end', $_POST, $this->EE->session->userdata['member_id']);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		}
	}
	
	
	private function redirect(){
		$this->EE->functions->redirect($this->zoo_settings['redirect_location']);
	}
	
	// ======================
	// = Autologin function =
	// ======================
	private function autologin($data, $member_id){
	
		// Log user in (the extra query is a little annoying)
		$this->EE->load->library('auth');
		$member_data_q = $this->EE->db->get_where('members', array('member_id' => $member_id));

		$incoming = new Auth_result($member_data_q->row());
		$incoming->remember_me(60*60*24*182);
		$incoming->start_session();

		$message = lang('mbr_your_are_logged_in');

	}

	// ==================================
	// = Activate Zoo Visitor Extension =
	// ==================================
	function activate_extension()
	{
		//if activation is set to none,
		//=>insert channel entry if activation is set to none, 
		//=>auto-login the member
		//=>if set, redirect to full profile page 

		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'sessions_end',
			'method'   => 'hook_sessions_end',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
				
		//if activation is set to email or manual, do not insert channel entry, wait for member validation
		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'member_member_register',
			'method'   => 'hook_member_member_register',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
		
		//change screenname + username to provided email
		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'member_member_register_start',
			'method'   => 'hook_member_member_register_start',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));		
		
		//=>insert channel entry 
		//=>auto-login the member
		//=>if set, redirect to full profile page 
		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'member_register_validate_members',
			'method'   => 'hook_member_register_validate_members',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
		
		//CP member actions
		
		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'cp_members_member_create',
			'method'   => 'hook_cp_members_member_create',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));

		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'cp_members_member_delete_end',
			'method'   => 'hook_cp_members_member_delete_end',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));

		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'cp_members_validate_members',
			'method'   => 'hook_cp_members_validate_members',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));

		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'safecracker_submit_entry_start',
			'method'   => 'hook_safecracker_submit_entry_start',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
				
		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'safecracker_submit_entry_end',
			'method'   => 'hook_safecracker_submit_entry_end',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));

		$this->EE->db->insert('extensions', array(
			'class'    => 'Zoo_visitor_ext',
			'hook'     => 'cp_js_end',
			'method'   => 'hook_cp_js_end',
			'settings' => '',
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));					
	}

	// ================================
	// = Update Zoo Visitor Extension =
	// ================================
	function update_extension($current = FALSE)
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		if ($current < '1.0.1')
		{
			$this->EE->db->insert('extensions', array(
				'class'    => 'Zoo_visitor_ext',
				'hook'     => 'cp_members_validate_members',
				'method'   => 'hook_cp_members_validate_members',
				'settings' => '',
				'priority' => 1,
				'version'  => $this->version,
				'enabled'  => 'y'
			));
		}
		
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->update(
					'extensions', 
					array('version' => $this->version)
		);
			
	}

	// ================================
	// = Disable Zoo Visitor Extension =
	// ================================
	function disable_extension()
	{
		$this->EE->db->query('DELETE FROM exp_extensions WHERE class = "Zoo_visitor_ext"');
	}

}
