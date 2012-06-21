<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* reCAPTCHA - Replaces EE2's built-in captcha with reCAPTCHA on member registration and comment forms
* 
* @package	Recaptcha
* @version	1.0.0
* @author	Brandon Jones
* @license	http://www.gnu.org/licenses/gpl.html
*/

class Recaptcha_ext
{
	var $name			= 'reCAPTCHA';
	var $version		= '1.0.0';
	var $description	= "Replaces EE2's built-in captcha with reCAPTCHA on member registration and comment forms";
	var $settings_exist	= 'y';
	var $docs_url		= '';
	var $settings		= array();


    // -------------------------------
    //   Constructor
    // -------------------------------
    
    function Recaptcha_ext($settings = '')
    {
	    $this->EE =& get_instance();

        $this->settings = $settings;
    }


	// --------------------------------
	//  Create captcha using reCAPTCHA
	// --------------------------------  
	
	function create_captcha()
	{
		// If settings are empty or wrong, try to fall back to the regular
		// captcha and hope they haven't removed its input field yet
		
		if ($this->check_settings() !== TRUE)
		{
			return;
		}

		// Load the stock recaptcha PHP library (v1.1 as of this writing)
		require_once('recaptchalib/recaptchalib.php');

		$this->EE->db->query("INSERT INTO exp_captcha (date, ip_address, word) VALUES (UNIX_TIMESTAMP(), '".$this->EE->input->ip_address()."', 'reCAPTCHA')");

		$theme = $this->settings['theme'];
		$language = $this->settings['language'];


		// EE's validation error page uses javascript for the "Return to Previous Page" link.
		// Different browsers cache form elements differently, which can cause the validation
		// request to be posted with an old challenge key and fail. Hence, recaptchaFix().

		$output = <<<EOD
			<script type="text/javascript">
				var RecaptchaOptions = {theme   : '$theme',
										lang    : '$language',
										callback: recaptchaFix};
				
				function recaptchaFix()
				{
					var container = document.getElementById('recaptcha_challenge_field_holder');
					container.innerHTML = '<input type="hidden" value="' + Recaptcha.get_challenge() +'" id="recaptcha_challenge_field" name="recaptcha_challenge_field">';
				}
			</script>
EOD;

		$output .= recaptcha_get_html(trim($this->settings['public_key']));
		
		$this->EE->extensions->end_script = TRUE;

		return $output;
	}


	// --------------------------------
	//  Validate captcha
	// --------------------------------  

	function validate_captcha()
	{
		// If settings are obviously wrong, try to fall back to the regular
		// captcha and hope they haven't already removed its input field
		
		if ($this->check_settings() !== TRUE)
		{
			return;
		}

		// Load the stock recaptcha PHP library (v1.1 as of this writing)
		require_once('recaptchalib/recaptchalib.php');

		$private_key = trim($this->settings['private_key']);

		$response = recaptcha_check_answer($private_key,
										   $this->EE->input->ip_address(),
										   $this->EE->input->post('recaptcha_challenge_field'),
										   $this->EE->input->post('recaptcha_response_field')
										  );
		
		if ($response->is_valid === TRUE)
		{
			// Give EE what it's looking for
			$_POST['captcha'] = 'reCAPTCHA';
		}
		else
		{
			// Ensure EE knows the captcha was invalid
			$_POST['captcha'] = '';

			// Whether the user's response was empty or just wrong, all we can do is make EE
			// think the captcha is missing, so we'll use more generic language for an error. 

			$this->EE->lang->loadfile('recaptcha');
			$this->EE->lang->language['captcha_required'] = $this->EE->lang->language['recaptcha_error'];
	
			if ($this->settings['debug'] == 'y') $this->EE->lang->language['captcha_required'] .= ' (' . $response->error . ')';
		}

		return;
	}


	// --------------------------------
	//  Check Settings
	// --------------------------------

	function check_settings()
	{
		// Have we been configured at all?
		if (count($this->settings) < 2)
		{
			return FALSE;
		}

		// Is either key obviously invalid?
		if (strlen(trim($this->settings['public_key'])) != 40 OR strlen(trim($this->settings['private_key'])) != 40)
		{
			return FALSE;
		}

		return TRUE;
	}

    
	// --------------------------------
	//  Activate Extension
	// --------------------------------
	
	function activate_extension()
	{
	    $this->EE->db->query($this->EE->db->insert_string('exp_extensions',
	                                  array(
	                                        'extension_id' => '',
	                                        'class'        => 'Recaptcha_ext',
	                                        'method'       => 'create_captcha',
	                                        'hook'         => 'create_captcha_start',
	                                        'settings'     => '',
	                                        'priority'     => 5,
	                                        'version'      => $this->version,
	                                        'enabled'      => 'y'
	                                      )
	                                 )
	              );

	    $this->EE->db->query($this->EE->db->insert_string('exp_extensions',
	                                  array(
	                                        'extension_id' => '',
	                                        'class'        => 'Recaptcha_ext',
	                                        'method'       => 'validate_captcha',
	                                        'hook'         => 'member_member_register_start',
	                                        'settings'     => '',
	                                        'priority'     => 1,
	                                        'version'      => $this->version,
	                                        'enabled'      => 'y'
	                                      )
	                                 )
	              );

	    $this->EE->db->query($this->EE->db->insert_string('exp_extensions',
	                                  array(
	                                        'extension_id' => '',
	                                        'class'        => 'Recaptcha_ext',
	                                        'method'       => 'validate_captcha',
	                                        'hook'         => 'insert_comment_start',
	                                        'settings'     => '',
	                                        'priority'     => 1,
	                                        'version'      => $this->version,
	                                        'enabled'      => 'y'
	                                      )
	                                 )
	              );

	}

	
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	
	function update_extension($current = '')
	{
		return TRUE;
	}


	// --------------------------------
	//  Disable Extension
	// --------------------------------  
	
	function disable_extension()
	{	    
		$this->EE->db->where('class', 'Recaptcha_ext');
    	$this->EE->db->delete('extensions');
	}


	// --------------------------------
	//  Settings
	// --------------------------------  	

	function settings()
	{
		$settings = array();

    	$settings['public_key'] 	= '';
    	$settings['private_key'] 	= '';
		$settings['language']		= array('s', array('en' => 'English', 'nl' => 'Dutch', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'ru' => 'Russian', 'es' => 'Spanish', 'tr' => 'Turkish'), 'en');
		$settings['theme'] 			= array('r', array('red' => 'Red', 'white' => 'White', 'blackglass' => 'Blackglass', 'clean' => 'Clean'), 'red');
		$settings['debug'] 			= array('r', array('n' => 'Don\'t Show', 'y' => 'Show'), 'n');

		return $settings;
	}
}
// END CLASS

/* End of file ext.recaptcha.php */
/* Location: ./system/expressionengine/third_party/recaptcha/ext.recaptcha.php */