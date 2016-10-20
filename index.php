<?php

class_exists('CApi') or die();

CApi::Inc('common.plugins.change-password');

class CcPanelChangePasswordPlugin extends AApiChangePasswordPlugin
{
	/**
	 * @var
	 */
	protected $oBaseApp;

	/**
	 * @var
	 */
	protected $oAdminAccount;

	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	public function __construct(CApiPluginManager $oPluginManager)
	{
		parent::__construct('1.0', $oPluginManager);
	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	protected function isLocalAccount($oAccount)
	{
		$account_imap = strtolower(trim($oAccount->IncomingMailServer));
		$cpanel_servers = CApi::GetConf('plugins.cpanel-change-password.config.servers', array(
		   'localhost', '127.0.0.1', '::1', '::1/128', '0:0:0:0:0:0:0:1'
		  ));
		if (is_array($cpanel_servers)) {
			return in_array($account_imap, $cpanel_servers);
		}
		else
		{
			return ($account_imap === $cpanel_servers);
		}
	}
	
	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	protected function validateIfAccountCanChangePassword($oAccount)
	{
		return ($this->isLocalAccount($oAccount));
	}

	/**
	 * @param CAccount $oAccount
	 */
	public function ChangePasswordProcess($oAccount)
	{
		if (0 < strlen($oAccount->PreviousMailPassword) &&
			$oAccount->PreviousMailPassword !== $oAccount->IncomingMailPassword)
		{
			
			$cpanel_hostname = CApi::GetConf('plugins.cpanel-change-password.config.hostname', 'localhost');
			$cpanel_username = CApi::GetConf('plugins.cpanel-change-password.config.username', 'local');
			$cpanel_password = CApi::GetConf('plugins.cpanel-change-password.config.password', '');

			$email_user = urlencode($oAccount->Email);
			$email_password = urlencode($oAccount->IncomingMailPassword);
			$email_domain = urlencode($oAccount->Domain->Name);

			$query = "https://".$cpanel_hostname.":2083/execute/Email/passwd_pop?email=".$email_user."&password=".$email_password."&domain=".$email_domain;

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
			curl_setopt($curl, CURLOPT_HEADER,0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
			$header[0] = "Authorization: Basic " . base64_encode($cpanel_username.":".$cpanel_password) . "\n\r";
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			curl_setopt($curl, CURLOPT_URL, $query);
			$result = curl_exec($curl);
			if ($result == false) {
				CApi::Log("curl_exec threw error \"" . curl_error($curl) . "\" for $query");
				curl_close($curl);
				throw new CApiManagerException(Errs::UserManager_AccountNewPasswordUpdateError);
			} else {
				curl_close($curl);
				$json_res = json_decode($result);
				if (!$json_res->status)
				{
					throw new CApiManagerException(Errs::UserManager_AccountNewPasswordUpdateError);
				}
			}
		}
	}
}

return new CcPanelChangePasswordPlugin($this);
