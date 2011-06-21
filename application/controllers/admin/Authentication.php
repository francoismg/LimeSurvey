<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Authentication extends AdminController {

	function __construct()
	{
		parent::__construct();
        
	}

	function index()
	{
		redirect('/admin', 'refresh');
	}
	
	function login()
	{
		if(!$this->session->userdata("loginID"))
		{
			//from login_check.php
			$sIp = $this->session->userdata("ip_address");
			$this->load->model("failed_login_attempts_model");
			$query = $this->failed_login_attempts_model->getAllRecords(array("ip"=>$sIp));
			//$query = "SELECT * FROM ".db_table_name('failed_login_attempts'). " WHERE ip='$sIp';";
			
			$bCannotLogin = false;
			$intNthAttempt = 0;
			//if ($result!==false && $result->RecordCount() >= 1)
			if ($query->num_rows() > 0)
			{
				$field = $query->row_array();//$result->FetchRow();
				$intNthAttempt = $field['number_attempts'];
				if ($intNthAttempt>=$this->config->item("maxLoginAttempt")){
					$bCannotLogin = true;
				}
				
				$iLastAttempt = strtotime($field['last_attempt']);
				
				if (time() > $iLastAttempt + $this->config->item("timeOutTime")){
					$bCannotLogin = false;
					//$query = "DELETE FROM ".db_table_name('failed_login_attempts'). " WHERE ip='$sIp';";
					$this->failed_login_attempts_model->deleteAttempts($sIp);
				}
			}
			
			if (!$bCannotLogin)
	        {
	        	if($this->input->post('action'))
	        	{
	        		self::_doLogin($sIp, $intNthAttempt);
	        	}
				else
				{
					self::_showLoginForm();
				}
			}
			else
			{
                // wrong or unknown username
                $data['errormsg']="";
                $data['maxattempts']=sprintf($this->limesurvey_lang->gT("You have exceeded you maximum login attempts. Please wait %d minutes before trying again"),($this->config->item("timeOutTime")/60))."<br />";
				$data['clang']=$this->limesurvey_lang;
				
				parent::_getAdminHeader();
				$this->load->view('admin/Authentication/error', $data);
				parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
			}
		}
		else
		{
			redirect('/admin', 'refresh');
		}
	}
	
	function logout()
	{
        killSession();
        // making dummy session variable check to destroy session completely!
        if($this->session->userdata('loginID'))
        $this->session->unset_userdata('loginID');
		//redirect(site_url('admin/Authentication/login'));
        self::_showLoginForm('<p>'.$this->limesurvey_lang->gT("Logout successful."));
	}
	
	function forgotpassword()
	{
		$clang = $this->limesurvey_lang;
		if(!$this->input->post("action"))
		{
			$data['clang'] = $this->limesurvey_lang;
			
			parent::_getAdminHeader();
			$this->load->view('admin/Authentication/forgotpassword', $data);
			parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));
		}
		else
		{
			$postuser = $this->input->post("user");
            $emailaddr = $this->input->post("email");
            //$query = "SELECT users_name, password, uid FROM ".db_table_name('users')." WHERE users_name=".$connect->qstr($postuser)." AND email=".$connect->qstr($emailaddr);			
            //$result = db_select_limit_assoc($query, 1) or safe_die ($query."<br />".$connect->ErrorMsg());  // Checked
            $this->load->model("Users_model");
			$query = $this->Users_model->getSomeRecords(array("users_name, password, uid"),array("users_name"=>$postuser,"email"=>$emailaddr));

            if ($query->num_rows()  < 1)
            {
                // wrong or unknown username and/or email
				$data['errormsg']=$this->limesurvey_lang->gT("User name and/or email not found!");
				$data['maxattempts']="";
				$data['clang']=$this->limesurvey_lang;
				
				parent::_getAdminHeader();
				$this->load->view('admin/Authentication/error', $data);
				parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
				
            }
            else
            {
                //$fields = $result->FetchRow();
				$fields = $query->row_array();

                // send Mail
                $new_pass = createPassword();
                $body = sprintf($clang->gT("Your user data for accessing %s"),$this->config->item("sitename")). "<br />\n";;
                $body .= $clang->gT("Username") . ": " . $fields['users_name'] . "<br />\n";
                $body .= $clang->gT("New password") . ": " . $new_pass . "<br />\n";

				$this->load->config("email");
                $subject = $clang->gT("User data","unescaped");
                $to = $emailaddr;
                $from = $this->config->item("siteadminemail");
                $sitename = $this->config->item("siteadminname");

                if(SendEmailMessage($body, $subject, $to, $from, $this->config->item("sitename"), false,$this->config->item("siteadminbounce")))
                {
                    //$query = "UPDATE ".db_table_name('users')." SET password='".SHA256::hashing($new_pass)."' WHERE uid={$fields['uid']}";
                    //$connect->Execute($query); //Checked
                    $this->Users_model->updatePassword($fields['uid'], $this->sha256->hashing($new_pass));
                    
					$data['clang'] = $clang;
					$data['message'] = "<br />".$clang->gT("Username").": {$fields['users_name']}<br />".$clang->gT("Email").": {$emailaddr}<br />
					<br />".$clang->gT("An email with your login data was sent to you.");
					parent::_getAdminHeader();
					$this->load->view('admin/Authentication/message', $data);
					parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
                }
                else
                {
                    $tmp = str_replace("{NAME}", "<strong>".$fields['users_name']."</strong>", $clang->gT("Email to {NAME} ({EMAIL}) failed."));
					$data['clang'] = $clang;
					$data['message'] = "<br />".str_replace("{EMAIL}", $emailaddr, $tmp) . "<br />";
					parent::_getAdminHeader();
					$this->load->view('admin/Authentication/message', $data);
					parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
					
                }
            }
       }
		
	}
	
	function _showLoginForm($logoutsummary="")
	{
		
		$refererargs=''; // If this is a direct access to admin.php, no args are given
        // If we are called from a link with action and other args set, get them
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'])
        {
            $refererargs = html_escape($_SERVER['QUERY_STRING']);
        }
		
		$data['refererargs'] = $refererargs;
		$data['clang'] = $this->limesurvey_lang;
		
		if ($logoutsummary=="")
		{
			$data['summary'] = $this->limesurvey_lang->gT("You have to login first.");
		}
		else
		{
			$data['summary'] = $logoutsummary;
		}
		
	    $lan=array();
		$this->load->helper("surveytranslator");
        foreach (getlanguagedata(true) as $langkey=>$languagekind)
        {
			array_push($lan,$langkey);
		}
		//The loop above stores all the language codes supported by LimeSurvey in a numeric array $lan.
		$x=0;
		while ($x<count($lan))
		{
			if ($lan[$x]=="zh-Hans")
			{
				$lan[$x]="zh";
			}
			elseif ($lan[$x]=="zh-Hant-HK")
			{
				$lan[$x]="zh-hk";
			}
			elseif ($lan[$x]=="zh-Hant-TW")
			{
				$lan[$x]="zh-tw";
			}
			elseif ($lan[$x]=="de-informal")
			{
				$lan[$x]="de";
			}
			elseif ($lan[$x]=="it-formal")
			{
				$lan[$x]="it";
			}
			elseif ($lan[$x]=="nl-informal")
			{
				$lan[$x]="nl";
			}
			else
			{
				$lan[$x]=strtolower($lan[$x]);
			}
			$x++;
		}
		
		//The above loop changes certain elements of $lan to the language codes which are supported by the browsers.
		$browlang="";
		if ( isset( $_SERVER["HTTP_ACCEPT_LANGUAGE"] ) )
		{
			$browlang=strtolower( $_SERVER["HTTP_ACCEPT_LANGUAGE"] );
			$browlang=str_replace(' ', '', $browlang);
			$browlang=explode( ",", $browlang);
			$browlang=$browlang[0];
			$browlang=explode( ";", $browlang);
			$browlang=$browlang[0];
			$check=0;
			$value=26;
			if ($browlang!="zh-hk" && $browlang!="zh-tw" && $browlang!="es-mx" && $browlang!="pt-br")
			{
				$browlang=explode( "-",$browlang);
				$browlang=$browlang[0];
			}
			//This if statement converts all the browser codes to Internet Explorer codes if the user is using Internet Explorer.
		}
		$x=0;
		$count=-1;
		while ($x<count($lan))
		{
			if ($browlang==$lan[$x])
			{
				$count=$x;
			}
			$x++;
		}
		if ($check==1)
		{
			$count=$value;
		}
		$data['count']=$count;

		parent::_getAdminHeader();
		$this->load->view('admin/Authentication/login', $data);
		parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
		
	}

	function _doLogin($sIp,$bLoginAttempted)
	{
		
        $clang = $this->limesurvey_lang;
		$postuser = sanitize_user($this->input->post("user"));
		//$query = "SELECT * FROM ".db_table_name('users')." WHERE users_name=".$connect->qstr($postuser);
		$this->load->model("Users_model");
		$query = $this->Users_model->getAllRecords(array("users_name"=>$postuser));

		//var_dump($query->row_array());
        //$result = $connect->SelectLimit($query, 1) or safe_die ($query."<br />".$connect->ErrorMsg());
        if ($query->num_rows() < 1)
        {
            //$query = fGetLoginAttemptUpdateQry($bLoginAttempted,$sIp);
            $this->load->model("failed_login_attempts_model");
			$query = $this->failed_login_attempts_model->addAttempt($bLoginAttempted,$sIp);

            //$result = $connect->Execute($query) or safe_die ($query."<br />".$connect->ErrorMsg());;
            if ($query)
            {
                // wrong or unknown username
                $data['errormsg']=$clang->gT("Incorrect username and/or password!");
				$data['maxattempts']="";
                if ($bLoginAttempted+1>=$this->config->item("maxLoginAttempt"))
                    $data['maxattempts']=sprintf($clang->gT("You have exceeded you maximum login attempts. Please wait %d minutes before trying again"),($this->config->item("timeOutTime")/60))."<br />";
				$data['clang']=$clang;
				
				parent::_getAdminHeader();
				$this->load->view('admin/Authentication/error', $data);
				parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
            }


        }
        else
        {
            $fields = $query->row_array();//$result->FetchRow();
            $this->load->library('admin/sha256','sha256');
            if ($this->sha256->hashing($this->input->post('password')) == $fields['password'])
            {
                // Anmeldung ERFOLGREICH
                if (strtolower($this->input->post('password'))=='password')
                {
                    $this->session->set_userdata('pw_notify',true);
				    $this->session->set_userdata('flashmessage',$clang->gT("Warning: You are still using the default password ('password'). Please change your password and re-login again."));
                }
                else
                {
                    $this->session->set_userdata('pw_notify',false);
                } // Check if the user has changed his default password

                /*if ($sessionhandler=='db')
                {
                    adodb_session_regenerate_id();
                }
                else
                {
                    session_regenerate_id();

                }*/
                $this->session->set_userdata('loginID',intval($fields['uid']));
                $this->session->set_userdata('user',$fields['users_name']);
                $this->session->set_userdata('full_name',$fields['full_name']);
                $this->session->set_userdata('htmleditormode',$fields['htmleditormode']);
                $this->session->set_userdata('templateeditormode',$fields['templateeditormode']);
                $this->session->set_userdata('questionselectormode',$fields['questionselectormode']);
                $this->session->set_userdata('dateformat',$fields['dateformat']);
                // Compute a checksession random number to test POSTs
                $this->session->set_userdata('checksessionpost',sRandomChars(10));
				
				$postloginlang=sanitize_languagecode($this->input->post('loginlang'));
                if (isset($postloginlang) && $postloginlang!='default')
                {
                    $this->session->set_userdata('adminlang',$postloginlang);
                    //$this->load->library('Limesurvey_lang',array("langcode"=>$postloginlang));
                    $this->limesurvey_lang->limesurvey_lang(array("langcode"=>$postloginlang));
                    $clang = $this->limesurvey_lang;
                    //$uquery = "UPDATE {$dbprefix}users "
                    //. "SET lang='{$postloginlang}' "
                    //. "WHERE uid={$_SESSION['loginID']}";
                    //$uresult = $connect->Execute($uquery);  // Checked
                    $this->Users_model->updateLang($this->session->userdata("loginID"),$postloginlang);
                }
                else
                {
                    $this->session->set_userdata('adminlang',$fields['lang']);
                    //$clang = new limesurvey_lang($fields['lang']);
					$this->load->library('Limesurvey_lang',array("langcode"=>$fields['lang']));
                    $clang = $this->limesurvey_lang;
                }
                $login = true;

                                    $loginsummary = "<br />".sprintf($clang->gT("Welcome %s!"),$this->session->userdata['full_name'])."<br />&nbsp;";

                if ($this->input->post('refererargs') && strpos($this->input->post('refererargs'), "action=logout") === FALSE)
                {
                	//require_once("../classes/inputfilter/class.inputfilter_clean.php");
                	$myFilter = new InputFilter('','',1,1,1);
                	// Prevent XSS attacks
                	//$sRefererArg=$myFilter->process($_POST['refererargs']);
                	$sRefererArg = $this->input->post('refererargs',true);
                    $this->session->set_userdata('metaHeader',"<meta http-equiv=\"refresh\""
                    . " content=\"1;URL={$scriptname}?".$sRefererArg."\" />");
                    $loginsummary = "<p><font size='1'><i>".$clang->gT("Reloading screen. Please wait.")."</i></font>\n";
                }
                self::_GetSessionUserRights($this->session->userdata('loginID'));
				// self::_showMessageBox($clang->gT("Logged in"), $loginsummary);
                $this->session->set_userdata("just_logged_in",true);
                $this->session->set_userdata('loginsummary',$loginsummary);
                redirect(site_url('/admin'));
            }
            else
            {
	            //$query = fGetLoginAttemptUpdateQry($bLoginAttempted,$sIp);
	            $this->load->model("failed_login_attempts_model");
				$query = $this->failed_login_attempts_model->addAttempt($bLoginAttempted,$sIp);
	
	            //$result = $connect->Execute($query) or safe_die ($query."<br />".$connect->ErrorMsg());;
	            if ($query)
	            {
	                // wrong or unknown username
	                $data['errormsg']=$clang->gT("Incorrect username and/or password!");
					$data['maxattempts']="";
	                if ($bLoginAttempted+1>=$this->config->item("maxLoginAttempt"))
	                    $data['maxattempts']=sprintf($clang->gT("You have exceeded you maximum login attempts. Please wait %d minutes before trying again"),($this->config->item("timeOutTime")/60))."<br />";
					$data['clang']=$clang;
					
					parent::_getAdminHeader();
					$this->load->view('admin/Authentication/error', $data);
					parent::_getAdminFooter("http://docs.limesurvey.org", $this->limesurvey_lang->gT("LimeSurvey online manual"));	
	            }
            }
        }
	}
}