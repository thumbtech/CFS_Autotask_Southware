<?php
// cfs_at_sw.php
// GreenPages Technology Solutions, Inc.
define ('VERSION', '1.2.6 04/24/2014');

define ('START_TIME', time());

// get command line arguments
for ($i=1; $i < $argc; $i++) {
	parse_str($argv[$i], $tmp);
	$_REQUEST = array_merge($_REQUEST, $tmp);
}

// need help ?
if(isset($_REQUEST['help'])) die(help_txt());

// need help ?
if(isset($_REQUEST['version'])) die('CFS Autotask / Southware Integration Script Version ' . VERSION . "\n");

// check for required command line arguments
$required_cli_params = array(
//	'account',
	);

$missing_cli_params = array ();	
foreach($required_cli_params as $v) if(!isset($_REQUEST[$v])) $missing_cli_params[] = $v;
if(!empty($missing_cli_params))
	write_out ("ERROR: Missing command line parameter(s) \"" . implode(', ', $missing_cli_params) . "\"\n\n" . help_txt(), 1, 1, __FILE__, __LINE__);
//print_r ($_REQUEST); exit;

// did user specify an ini file? ... if not use default
if (!isset($_REQUEST['ini'])) $_REQUEST['ini'] = basename(__FILE__, 'php') . 'ini';

// parse the ini file
if (!$ini = @parse_ini_file($_REQUEST['ini']))
	write_out ("ERROR: Unable to parse {$_REQUEST['ini']}", 1, 1, __FILE__, __LINE__);
	
// timezone 
$timezone = 'America/New_York';
if (isset ($ini['timezone'])) $timezone = $ini['timezone'];
date_default_timezone_set ($timezone);

// mail settings
if (isset ($ini['smtp_host'])) ini_set ('SMTP', $ini['smtp_host']);
if (isset ($ini['smtp_port'])) ini_set ('smtp_port', $ini['smtp_port']);
if (isset ($ini['smtp_from'])) ini_set ('sendmail_from', $ini['smtp_from']);

// test mail?
if (isset ($_REQUEST['test_mail'])) {
	if (!isset ($ini['notify_results'])) {
		echo "notify_results directive not set!\n";
	} elseif (validate_email_addresses ($ini['notify_results'])) {
		echo "===========================================================================\n";
		$message = "Test sent " . date ('Y-m-d H:i:s');
		$sent = mail ($ini['notify_results'], 'CFS Autotask / Southware Script Test', $message);
		if ($sent) {
			echo "Test message sent to {$ini['notify_results']}\n";
		} else {
			echo "Unable to send test message to {$ini['notify_results']}\n";
		}
	} else {
		echo "\"{$ini['notify_results']}\" not valid email address(es)!\n";
	}
	write_out ('', 0, 1);
}
	
// test error mail?
if (isset ($_REQUEST['test_error_mail'])) {
	if (!isset ($ini['notify_errors'])) {
		echo "notify_errors directive not set!\n";
	} elseif (validate_email_addresses ($ini['notify_errors'])) {
		echo "===========================================================================\n";
		$message = "Test sent " . date ('Y-m-d H:i:s');
		$sent = mail ($ini['notify_errors'], 'CFS Autotask / Southware Script Test', $message);
		if ($sent) {
			echo "Test message sent to {$ini['notify_errors']}\n";
		} else {
			echo "Unable to send test message to {$ini['notify_errors']}\n";
		}
	} else {
		echo "\"{$ini['notify_errors']}\" not valid email address(es)!\n";
	}
	write_out ('', 0, 1);
}
	

// check for required directives in parsed ini file
$required_ini_vars = array(
	'autotask_api_user',
	'autotask_api_password',
	'autotask_api_zone',
	'invoices_account_id_field',
	'invoices_account_number_field',
	'invoices_autotask_dir',
	'invoices_autotask_archive_dir',
	'invoices_southware_dir',
	'accounts_southware_dir',
	'accounts_southware_archive_dir',
	'accounts_autotask_dir',
	'contracts_southware_dir',
	'contracts_southware_archive_dir',
	);

$missing_ini_vars = array ();	
foreach($required_ini_vars as $v) if(!isset($ini[$v])) $missing_ini_vars[] = $v;
if(!empty($missing_ini_vars))
	write_out ("ERROR: Missing ini directive(s) \"" . implode(', ', $missing_ini_vars) . "\" in {$_REQUEST['ini']}", 1, 1, __FILE__, __LINE__);
//print_r ($ini); exit;

// now we can get to work ...
// set the autotask wsdl for the zone
$ini['autotask_api_wsdl'] = "https://webservices{$ini['autotask_api_zone']}.autotask.net/atservices/1.5/atws.wsdl";

// headers arrays of name, context, value
$meta = array (
	array ('Autotask Account ID', 'account', 'id'),
	array ('Customer Number', 'account', 'AccountNumber'),
//	array ('Customer Inactive?', 'account', 'Active'),
//	array ('Account Type', 'account', 'AccountType'),
	array ('Customer Name', 'account', 'AccountName'),
	array ('Address Line 1', 'account', 'Address1'),
	array ('Address Line 2', 'account', 'Address2'),
	array ('City', 'account', 'City'),
	array ('State', 'account', 'State'),
	array ('Zip Code', 'account', 'PostalCode'),
	array ('Contact', 'contact', 'name'),
	array ('EMail Address', 'contact', 'email'),
	array ('Phone Number', 'account', 'Phone'),
	array ('Fax No', 'account', 'Fax'),
	array ('Salesperson Number', 'account', 'OwnerResourceID'),
	array ('Salesperson Name', 'owner', 'name'),
	array ('Salesperson Initials', 'owner', 'initials'),
	array ('Customer Type', 'account', 'MarketSegmentID'),
//	array ('Balance Method [O,B]', 'literal', 'O'),
//	array ('Statement Frequency', 'literal', 'M'),
//	array ('Credit Limit', 'literal', ''),
//	array ('Credit Rating', 'literal', ''),
	array ('Apply Finance Chrgs?', 'literal', 'N'),
	array ('Ship Via Code', 'literal', 'DEL'),
//	array ('Requires P/O ? Y/N', 'literal', ''),
	array ('Group Number', 'account', 'ParentAccountID'),
//	array ('Price Level', 'literal', '1'),
//	array ('Terms Code', 'literal', ''),
	array ('Tax Code', 'literal', ''),
	array ('Date Customer Added', 'calc', "date('m/d/y')"),
//	array ('Allow Subs?', 'literal', 'N'),
//	array ('Allow B/Os?', 'literal', 'Y'),
//	array ('Tax #', 'literal', ''),
//	array ('Allow Partial Ship?', 'literal', 'N S'),
//	array ('Remind to Fax?', 'literal', 'N'),
//	array ('Bill Shipping?', 'literal', 'Y'),
	array ('Web Address', 'account', 'WebAddress'),
//	array ('Territory', 'account', 'TerritoryID'),
//	array ('Key Account Icon', 'account', 'KeyAccountIcon'),
	array ('Tax Region Number', 'account', 'TaxRegionID'),
	array ('Tax Region Name', 'taxregion', 'name'),
	);

// fields to match for contracts
$meta_contract_fields = array (
	'CLIENT NUMBER',
	'EXTERNAL INVOICE NUMBER',
	'ALLOCATION CODE EXTERNAL NUMBER',
	'EXTENDED PRICE',
	'QUANTITY',
	'EXTERNAL CONTRACT NUMBER',
	'ITEM NUMBER',
	'SERVICE PERIOD START DATE',
	'SERVICE PERIOD END DATE',
	'BILL TO CLIENT',
	'BILL TO CLIENT ADDRESS 1',
	'BILL TO CLIENT ADDRESS 2',
	'BILL TO CLIENT CITY',
	'BILL TO CLIENT STATE',
	'BILL TO CLIENT ZIP CODE',
	);
	
// contract matches
if (isset ($ini['contracts_allocation_code'])) {
	$ini['contracts_allocation_code'] = explode (',', $ini['contracts_allocation_code']);
	foreach ($ini['contracts_allocation_code'] as $k => $v) if (empty ($v)) unset ($ini['contracts_allocation_code'][$k]);
} else {
	$ini['contracts_allocation_code'] = array ();
}

// OK! Connect to AT:
// Go get authdata!
$options = array (
	'login' => $ini['autotask_api_user'],
	'password' => $ini['autotask_api_password'],
	);
try {
	// set_time_limit (30);
	$soapClient = new SoapClient ($ini['autotask_api_wsdl'], $options);
} catch (Exception $e) {  
	write_out ("ERROR: Failed connecting to Autotask: " . $e->getMessage(), 1, 1, __FILE__, __LINE__); 
}

// as we process invoices, we will be keeping track of the accounts we have matched and the ones needed!
$accts_matched = array ();
$accts_needed = array ();

// ... also contracts
$contracts_matched = array ();
$contracts_needed = array ();

// to get contract numbers I'll be matching by task & ticket numbers, too
$tasks_matched = array ();
$tickets_matched = array ();

// but first we need to update account numbers ... southware -> autotask!
if (!is_dir ($ini['accounts_southware_dir'])) write_out ("ERROR: Cannot locate accounts_southware_dir @ \"{$ini['accounts_southware_dir']}\"", 1, 1, __FILE__, __LINE__);
if (!is_dir ($ini['accounts_southware_archive_dir'])) write_out ("ERROR: Cannot locate accounts_southware_archive_dir @ \"{$ini['accounts_southware_archive_dir']}\"", 1, 1, __FILE__, __LINE__);
write_out ("Checking for updated Southware accounts in {$ini['accounts_southware_dir']}");
$sw_files = scandir ($ini['accounts_southware_dir']);
foreach ($sw_files as $sw_file) {
	if (substr ($sw_file, 0, 1) == '.') continue;
	if (!is_file ($ini['accounts_southware_dir'] . '/' . $sw_file)) continue;
	if (!$sw_accounts = @file_get_contents ($ini['accounts_southware_dir'] . '/' . $sw_file)) write_out ("ERROR: Unable to read \"{$ini['accounts_southware_dir']}/{$sw_file}\"", 1, 1, __FILE__, __LINE__);
	write_out ("Updating accounts from  {$sw_file}");
	$sw_accounts = explode ("\r\n", $sw_accounts);
	$acct_cnt = 0;
	$acct_err = 0;
	foreach ($sw_accounts as $sw_account) {
		$sw_account_fields = explode ("\t", $sw_account);
		if (count ($sw_account_fields) != 2) continue;
		$acct_cnt++;
		// find account in autotask!		
		$xml = xmlwriter_open_memory();
		xmlwriter_start_document ($xml);
		xmlwriter_start_element($xml, 'queryxml');
			xmlwriter_start_element($xml, 'entity');
				xmlwriter_text ($xml, 'Account');
			xmlwriter_end_element($xml);
			xmlwriter_start_element($xml, 'query');
				xmlwriter_start_element($xml, 'condition');
					xmlwriter_start_element($xml, 'field');
						xmlwriter_text ($xml, 'id');
						xmlwriter_start_element($xml, 'expression');
							xmlwriter_write_attribute ($xml, 'op', 'equals');
							xmlwriter_text ($xml, $sw_account_fields[1]);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
			xmlwriter_end_element($xml);
		xmlwriter_end_element($xml);
		xmlwriter_end_document ($xml);
		$xml = xmlwriter_output_memory ($xml);
		
		$params = array ('sXML' => $xml);
		
		try {
			// set_time_limit (30);
			$soapResponse = $soapClient->query($params);
			// print_r ($soapResponse); exit;
		} catch (SoapFault $e) {
			write_out ("ERROR: SOAP fault on Autotask account query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
		}
		
		$entities_a = array ();
		if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_a = $soapResponse->queryResult->EntityResults->Entity;
		if (!is_array ($entities_a)) $entities_a = array ($entities_a);
		// print_r ($entities_a); exit;
		
		// found account?
		if (empty ($entities_a)) {
			write_out ("WARNING: Account {$sw_account_fields[1]} not found in Autotask ... account update skipped!", 1, 0, __FILE__, __LINE__);
			$acct_err++;
			continue;
		}
		
		// update the account(s) (just 1 in this case)
		foreach ($entities_a as $er_a) {
		
			$aObj = new stdClass();
			$aObj->id = $sw_account_fields[1];
			$aObj->AccountNumber = new SoapVar($sw_account_fields[0], XSD_STRING);
			
			$vars = get_object_vars($er_a); // print_r ($vars); exit;
			foreach ($vars as $k => $v) {
				if (in_array ($k, array ('id', 'AccountNumber', 'CreateDate', 'LastActivityDate'))) continue;
				if (is_object ($er_a->$k)) continue;
				$v = trim ($v);
				if (strstr ($k, 'Active') !== false) {
					$aObj->$k = !empty ($er_a->$k);
				} elseif ($v == '') {
					$aObj->$k = null;
				} else {
					$aObj->$k = new SoapVar($v, XSD_STRING);
				}
			}
			
			$sObj = new SoapVar($aObj, SOAP_ENC_OBJECT, 'Account', 'http://autotask.net/ATWS/v1_5/');
			$entArray = array($sObj);
			$ents = new SoapVar($entArray, SOAP_ENC_OBJECT, 'ArrayOfEntity', 'http://autotask.net/ATWS/v1_5/');
			// print_r ($ents); exit;

			try {
				// set_time_limit (30);
				$soapResponse = $soapClient->update(new SoapParam(array('Entities'=>$ents), 'Entities'));
			} catch (SoapFault $e) {
				write_out ("Error updating Autotask account info. Received SOAP Fault: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
			}
			// print_r ($soapResponse); exit;
			
			$return = (int) $soapResponse->updateResult->ReturnCode;
			
			if ($return == 1) {
				write_out ("Updated account \"{$er_a->AccountName}\"");
			} else {
				$errors = array ();
				foreach ($soapResponse->updateResult->Errors->ATWSError as $e) $errors[] = (string) $e->Message;
				write_out ("WARNING: Error updating {$sw_account_fields[1]}: " . implode (' / ', $errors), 1, 0, __FILE__, __LINE__);
				$acct_err++;
				continue;
			}
			// record the match!
			$accts_matched[$sw_account_fields[1]] = $sw_account_fields[0];
		} // end each account to update
	} // end each account in the southware file
	if ($acct_cnt == 0) {
		write_out ("No valid account records found in {$sw_file} ... Please validate its format. File NOT archived.", 1, 0, __FILE__, __LINE__);
	} elseif ($acct_err > 0) {
		write_out ("Errors encountered while processing {$sw_file} ... File NOT archived.", 1, 0, __FILE__, __LINE__);
	} else {
		write_out ("{$acct_cnt} account records updated with Southware account numbers from {$sw_file}");
		if (@rename ($ini['accounts_southware_dir'] . '/' . $sw_file, $ini['accounts_southware_archive_dir'] . '/' . $sw_file)) {
			write_out ("Archived {$sw_file} in {$ini['accounts_southware_archive_dir']}");
		} else {
			write_out ("Unable to archive/move {$sw_file} to {$ini['accounts_southware_archive_dir']}", 1, 0, __FILE__, __LINE__);
		}
	}
} // end each southware file

// ... and second we need to update contract numbers ... southware -> autotask!
if (!is_dir ($ini['contracts_southware_dir'])) write_out ("ERROR: Cannot locate contracts_southware_dir @ \"{$ini['contracts_southware_dir']}\"", 1, 1, __FILE__, __LINE__);
if (!is_dir ($ini['contracts_southware_archive_dir'])) write_out ("ERROR: Cannot locate contracts_southware_archive_dir @ \"{$ini['contracts_southware_archive_dir']}\"", 1, 1, __FILE__, __LINE__);
write_out ("Checking for updated Southware contracts in {$ini['contracts_southware_dir']}");
$sw_files = scandir ($ini['contracts_southware_dir']);
foreach ($sw_files as $sw_file) {
	if (substr ($sw_file, 0, 1) == '.') continue;
	if (!is_file ($ini['contracts_southware_dir'] . '/' . $sw_file)) continue;
	if (!$sw_contracts = @file_get_contents ($ini['contracts_southware_dir'] . '/' . $sw_file)) write_out ("ERROR: Unable to read \"{$ini['contracts_southware_dir']}/{$sw_file}\"", 1, 1, __FILE__, __LINE__);
	write_out ("Updating contracts from  {$sw_file}");
	$sw_contracts = explode ("\r\n", $sw_contracts);
	$cont_cnt = 0;
	$cont_err = 0;
	foreach ($sw_contracts as $sw_contract) {
		$sw_contract_fields = explode ("\t", $sw_contract);
		if (count ($sw_contract_fields) != 2) continue;
		$cont_cnt++;
		// find contract in autotask!		
		$xml = xmlwriter_open_memory();
		xmlwriter_start_document ($xml);
		xmlwriter_start_element($xml, 'queryxml');
			xmlwriter_start_element($xml, 'entity');
				xmlwriter_text ($xml, 'Contract');
			xmlwriter_end_element($xml);
			xmlwriter_start_element($xml, 'query');
				xmlwriter_start_element($xml, 'condition');
					xmlwriter_start_element($xml, 'field');
						xmlwriter_text ($xml, 'id');
						xmlwriter_start_element($xml, 'expression');
							xmlwriter_write_attribute ($xml, 'op', 'equals');
							xmlwriter_text ($xml, $sw_contract_fields[1]);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
			xmlwriter_end_element($xml);
		xmlwriter_end_element($xml);
		xmlwriter_end_document ($xml);
		$xml = xmlwriter_output_memory ($xml);
		
		$params = array ('sXML' => $xml);
		
		try {
			// set_time_limit (30);
			$soapResponse = $soapClient->query($params);
			// print_r ($soapResponse); exit;
		} catch (SoapFault $e) {
			write_out ("ERROR: SOAP fault on Autotask contract query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
		}
		
		$entities_a = array ();
		if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_a = $soapResponse->queryResult->EntityResults->Entity;
		if (!is_array ($entities_a)) $entities_a = array ($entities_a);
		// print_r ($entities_a); exit;
		
		// found contract?
		if (empty ($entities_a)) {
			write_out ("WARNING: Contract {$sw_contract_fields[1]} not found in Autotask ... contract update skipped!", 1, 0, __FILE__, __LINE__);
			$cont_err++;
			continue;
		}
		
		// update the contract(s) (just 1 in this case)
		foreach ($entities_a as $er_a) {
		
			$aObj = new stdClass();
			$aObj->id = $sw_contract_fields[1];
			$aObj->ContractNumber = new SoapVar($sw_contract_fields[0], XSD_STRING);
			
			$vars = get_object_vars($er_a); // print_r ($vars); exit;
			foreach ($vars as $k => $v) {
				if (in_array ($k, array ('id', 'ContractNumber', 'CreateDate', 'LastActivityDate'))) continue;
				if (is_object ($er_a->$k)) continue;
				$v = trim ($v);
				if (strstr ($k, 'Compliance') !== false or strstr ($k, 'IsDefaultContract') !== false) {
					$aObj->$k = !empty ($er_a->$k);
				} elseif ($v == '') {
					$aObj->$k = null;
				} else {
					$aObj->$k = new SoapVar($v, XSD_STRING);
				}
			}
			
			$sObj = new SoapVar($aObj, SOAP_ENC_OBJECT, 'Contract', 'http://autotask.net/ATWS/v1_5/');
			$entArray = array($sObj);
			$ents = new SoapVar($entArray, SOAP_ENC_OBJECT, 'ArrayOfEntity', 'http://autotask.net/ATWS/v1_5/');
			// print_r ($ents); exit;

			try {
				// set_time_limit (30);
				$soapResponse = $soapClient->update(new SoapParam(array('Entities'=>$ents), 'Entities'));
			} catch (SoapFault $e) {
				write_out ("Error updating Autotask contract info. Received SOAP Fault: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
			}
			// print_r ($soapResponse); exit;
			
			$return = (int) $soapResponse->updateResult->ReturnCode;
			
			if ($return == 1) {
				write_out ("Updated contract \"{$er_a->ContractName}\"");
			} else {
				$errors = array ();
				foreach ($soapResponse->updateResult->Errors->ATWSError as $e) $errors[] = (string) $e->Message;
				write_out ("WARNING: Error updating {$sw_contract_fields[1]}: " . implode (' / ', $errors), 1, 0, __FILE__, __LINE__);
				$cont_err++;
				continue;
			}
			// record the match!
			$contracts_matched[$sw_contract_fields[1]] = $sw_contract_fields[0];
		} // end each contract to update
	} // end each contract in the southware file
	if ($cont_cnt == 0) {
		write_out ("No valid contract records found in {$sw_file} ... Please validate its format. File NOT archived.", 1, 0, __FILE__, __LINE__);
	} elseif ($cont_err > 0) {
		write_out ("Errors encountered while processing contracts in {$sw_file} ... File NOT archived.", 1, 0, __FILE__, __LINE__);
	} else {
		write_out ("{$cont_cnt} contract records updated with Southware contract numbers from {$sw_file}");
		if (@rename ($ini['contracts_southware_dir'] . '/' . $sw_file, $ini['contracts_southware_archive_dir'] . '/' . $sw_file)) {
			write_out ("Archived {$sw_file} in {$ini['contracts_southware_archive_dir']}");
		} else {
			write_out ("Unable to archive/move {$sw_file} to {$ini['contracts_southware_archive_dir']}", 1, 0, __FILE__, __LINE__);
		}
	}
} // end each southware file

// any invoice files to process?
if (!is_dir ($ini['invoices_autotask_dir'])) write_out ("ERROR: Cannot locate invoices_autotask_dir @ \"{$ini['invoices_autotask_dir']}\"", 1, 1, __FILE__, __LINE__);
if (!is_dir ($ini['invoices_autotask_archive_dir'])) write_out ("ERROR: Cannot locate invoices_autotask_archive_dir @ \"{$ini['invoices_autotask_archive_dir']}\"", 1, 1, __FILE__, __LINE__);
write_out ("Checking for Autotask invoice exports in {$ini['invoices_autotask_dir']}");

// do I need to exclude any columns from my output?
$exclude_at_columns = '';
if (isset ($ini['invoice_skip_columns'])) $exclude_at_columns = $ini['invoice_skip_columns'];
if (isset ($_REQUEST['invoice_skip_columns'])) $exclude_at_columns = $_REQUEST['invoice_skip_columns'];
if (empty ($exclude_at_columns)) {
	$exclude_at_columns = array ();
} else {
	$exclude_at_columns = explode (',', $exclude_at_columns);
}

// now look for files
$at_files = scandir ($ini['invoices_autotask_dir']);
foreach ($at_files as $at_file) {
	if (substr ($at_file, 0, 1) == '.') continue;
	if (!is_file ($ini['invoices_autotask_dir'] . '/' . $at_file)) continue;
	if (!$fhi = @fopen ($ini['invoices_autotask_dir'] . '/' . $at_file, 'r')) write_out ("ERROR: Unable to read \"{$ini['invoices_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
	
	// create temp output file in archive directory
	$tmp_at_out = tempnam($ini['invoices_autotask_archive_dir'], 'CFS');
	if (!$fho = @fopen ($tmp_at_out , 'w')) write_out ("ERROR: Unable to create temporary file in \"{$ini['invoices_autotask_archive_dir']}\"", 1, 1, __FILE__, __LINE__);

	write_out ("Processing invoices from {$at_file} ...");
	// process header each time ... maybe the files are in different formats!?!
	$header = 0;
	$acct_id_fld = -1;
	$acct_no_fld = -1;
	$contract_field = -1;
	$contract_no_field = -1;
	$contract_invoice_field = -1;
	$contract_ticket_field = -1;
	$contract_inv_tcks = array ();
	$truncate_at_fld = -1;
	$exclude_at_flds = array ();
	$contract_at_flds = array ();
	$contract_records = 0;
	$rows = 0;
	$incompletes = 0; // how many incomplete rows in _this_ file?

	while ( ($data = fgetcsv($fhi) ) !== FALSE ) {
		$datac = array ();
		if ($header == 0) {
			$data = preg_replace('/[^A-Za-z0-9 ]/s', '', $data); // clean out the nasty extras pulled down from AT
			$header = count ($data);
			foreach ($data as $k => $v) {
				if (in_array ($v, $meta_contract_fields)) {
					$contract_at_flds[] = $k;
					$datac[] = $v;
				}
				if ($v == $ini['invoices_account_id_field']) $acct_id_fld = $k;
				if ($v == $ini['invoices_account_number_field']) $acct_no_fld = $k;
				if ($v == 'EXTERNAL CONTRACT NUMBER') $contract_no_field = $k;
				if ($v == 'INVOICEID') $contract_invoice_field = $k;
				if ($v == 'TASK OR TICKET NUMBER') $contract_ticket_field = $k;
				if (!empty ($ini['contracts_allocation_code']) and $v == 'ALLOCATION CODE EXTERNAL NUMBER') $contract_field = $k;
				if (isset ($ini['invoice_truncate_column']) and $v == $ini['invoice_truncate_column']) $truncate_at_fld = $k;
				if (in_array ($v, $exclude_at_columns)) $exclude_at_flds[] = $k;
			}
			if ($acct_id_fld == -1) write_out ("ERROR: Unable to locate {$ini['invoices_account_id_field']} header (set as invoices_account_id_field in \"{$_REQUEST['ini']}\") in \"{$ini['invoices_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
			if ($acct_no_fld == -1) write_out ("ERROR: Unable to locate {$ini['invoices_account_number_field']} header (set as invoices_account_number_field in \"{$_REQUEST['ini']}\") in \"{$ini['invoices_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
			if ($contract_invoice_field == -1) write_out ("ERROR: Unable to locate INVOICEID header in \"{$ini['invoices_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
			if ($contract_ticket_field == -1) write_out ("ERROR: Unable to locate TASK OR TICKET NUMBER header in \"{$ini['invoices_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
			// set up clean csv for southware
			foreach ($exclude_at_flds as $v) unset ($data[$v]);
			foreach ($data as $k => $v) $data[$k] = '_BEGFLD_' . $v . '_ENDFLD_';
			$data[] = '_ENDLIN_';
			fputcsv ($fho, $data);
			continue;
		}
		if (empty ($data)) continue;
		$rows++;
		if (count ($data) != $header) {
			// try the "self healing" technicque!
			if ($truncate_at_fld >=0 and count ($data) > $header) {
				$i = $truncate_at_fld;
				while (true) {
					$i++;
					unset ($data[$i]);
					if (count ($data) <= $header) break;
				}
			} else {
				write_out ("ERROR: Column count mismatch in data row {$rows} of \"{$ini['invoices_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
			}
		}
		
		// now check for accounts in file!
		if (!empty ($data[$contract_invoice_field]) and empty ($data[$acct_no_fld])) {
			if (isset ($accts_matched[$data[$acct_id_fld]])) {
				$data[$acct_no_fld] = $accts_matched[$data[$acct_id_fld]];
			} elseif (isset ($accts_needed[$data[$acct_id_fld]])) {
				// OK I already know I'm going to need to get this one
				$incompletes++;
			} else {
				// find account and see if it has an AccountNumber
				write_out ("Querying Autotask for account {$data[$acct_id_fld]} ...");
				$xml = xmlwriter_open_memory();
				xmlwriter_start_document ($xml);
				xmlwriter_start_element($xml, 'queryxml');
					xmlwriter_start_element($xml, 'entity');
						xmlwriter_text ($xml, 'Account');
					xmlwriter_end_element($xml);
					xmlwriter_start_element($xml, 'query');
						xmlwriter_start_element($xml, 'condition');
							xmlwriter_start_element($xml, 'field');
								xmlwriter_text ($xml, 'id');
								xmlwriter_start_element($xml, 'expression');
									xmlwriter_write_attribute ($xml, 'op', 'equals');
									xmlwriter_text ($xml, $data[$acct_id_fld]);
								xmlwriter_end_element($xml);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
				xmlwriter_end_document ($xml);
				$xml = xmlwriter_output_memory ($xml);
				
				$params = array ('sXML' => $xml);
				
				try {
					// set_time_limit (30);
					$soapResponse = $soapClient->query($params);
					// print_r ($soapResponse); exit;
				} catch (SoapFault $e) {
					write_out ("ERROR: SOAP fault on Autotask account query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
				}
				
				$entities_a = array ();
				if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_a = $soapResponse->queryResult->EntityResults->Entity;
				if (!is_array ($entities_a)) $entities_a = array ($entities_a);
				
				if (empty ($entities_a)) write_out ("Account ID #{$data[$acct_id_fld]} referenced in {$at_file} not found in Autotask?!?", 1, 1, __FILE__, __LINE__);
				
				// match it if I got it!
				foreach ($entities_a as $er_a) {
					if (empty ($er_a->AccountNumber)) continue;
					$v = (string) $er_a->AccountNumber;
					if (empty ($v)) continue;
					$accts_matched[$data[$acct_id_fld]] = $v;
				}
				
				// did I get it?
				if (isset ($accts_matched[$data[$acct_id_fld]])) {
					$data[$acct_no_fld] = $accts_matched[$data[$acct_id_fld]];
				} else {
					// need this account! Save the data for use later ...
					$acct_data = array ();
					foreach ($meta as $v) {
						switch ($v[1]) {
							case 'account':
								if (isset ($er_a->$v[2])) $acct_data[$v[2]] = (string) $er_a->$v[2];
								break;
						}
					}					
					$accts_needed[$data[$acct_id_fld]] = $acct_data;
					$incompletes++;
				}
			}
		} // end if need account
		
		// now check for contracts in file!
		if (!empty ($data[$contract_invoice_field]) and $contract_field >=0 and $contract_no_field >=0 and in_array ($data[$contract_field], $ini['contracts_allocation_code']) and empty ($data[$contract_no_field]) and !isset ($accts_needed[$data[$acct_id_fld]])) {
			// OK ... what is the contract id?
			// what's the billing item key?:
			$bi_key = $data[$contract_invoice_field] . $data[$contract_ticket_field];
			if (isset ($contract_inv_tcks[$bi_key])) {
				$contract_id = $contract_inv_tcks[$bi_key];
			} else {
				// query the billing items!
				write_out ("Querying Autotask for billing items for invoice {$data[$contract_invoice_field]} ...");
				$xml = xmlwriter_open_memory();
				xmlwriter_start_document ($xml);
				xmlwriter_start_element($xml, 'queryxml');
					xmlwriter_start_element($xml, 'entity');
						xmlwriter_text ($xml, 'BillingItem');
					xmlwriter_end_element($xml);
					xmlwriter_start_element($xml, 'query');
						xmlwriter_start_element($xml, 'condition');
							xmlwriter_start_element($xml, 'field');
								xmlwriter_text ($xml, 'InvoiceID');
								xmlwriter_start_element($xml, 'expression');
									xmlwriter_write_attribute ($xml, 'op', 'equals');
									xmlwriter_text ($xml, $data[$contract_invoice_field]);
								xmlwriter_end_element($xml);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
				xmlwriter_end_document ($xml);
				$xml = xmlwriter_output_memory ($xml);
				
				$params = array ('sXML' => $xml);
				
				try {
					// set_time_limit (30);
					$soapResponse = $soapClient->query($params);
					// print_r ($soapResponse); exit;
				} catch (SoapFault $e) {
					write_out ("ERROR: SOAP fault on Autotask account query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
				}
				
				$entities_a = array ();
				if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_a = $soapResponse->queryResult->EntityResults->Entity;
				if (!is_array ($entities_a)) $entities_a = array ($entities_a);
				
				if (empty ($entities_a)) write_out ("Invoice ID #{$data[$contract_invoice_field]} referenced in {$at_file} not found in Autotask?!?", 1, 1, __FILE__, __LINE__);
				
				// loop through the items and do a task, ticket, or "raw" match to the contract id in the billing item ...
				foreach ($entities_a as $er_a) {
					$t_bi_key = $data[$contract_invoice_field];
					$task = '';
					if (!empty($er_a->TaskID)) $task = (string) $er_a->TaskID;
					$ticket = '';
					if (!empty($er_a->TicketID)) $ticket = (string) $er_a->TicketID;
					if (!empty ($task)) {
						$t_bi_key .= get_task_ticket ($task, 'Task');
					} elseif (!empty ($ticket)) {
						$t_bi_key .= get_task_ticket ($ticket, 'Ticket');
					}
					if (isset ($contract_inv_tcks[$t_bi_key])) continue; // already have it!
					$contract_inv_tcks[$t_bi_key] = (string) $er_a->ContractID;
				}
				
				// OK! Hopefully I have it now
				if (!isset ($contract_inv_tcks[$bi_key])) write_out ("Unable to determine external contract number(s) for Invoice ID #{$data[$contract_invoice_field]} referenced in {$at_file}. These may need to be manually set?!?", 1, 1, __FILE__, __LINE__);
				
				// Have it now!
				$contract_id = $contract_inv_tcks[$bi_key];
			}
			
			// but do we really have it? ... or is it just a blank contract?
			if (empty ($contract_id)) write_out ("Unable to determine external contract number(s) for Invoice ID #{$data[$contract_invoice_field]} referenced in {$at_file}. These may need to be manually set?!?", 1, 1, __FILE__, __LINE__);
			
			// now I have the contract id for the billing item ... proceed:
			if (isset ($contracts_matched[$contract_id])) {
				$data[$contract_no_field] = $contracts_matched[$contract_id];
			} elseif (isset ($contracts_needed[$contract_id])) {
				// OK I already know I'm going to need to get this one
				$incompletes++;
			} else {
				// find contract and see if it has an ContractNumber
				write_out ("Querying Autotask for contract {$contract_id} ...");
				$xml = xmlwriter_open_memory();
				xmlwriter_start_document ($xml);
				xmlwriter_start_element($xml, 'queryxml');
					xmlwriter_start_element($xml, 'entity');
						xmlwriter_text ($xml, 'Contract');
					xmlwriter_end_element($xml);
					xmlwriter_start_element($xml, 'query');
						xmlwriter_start_element($xml, 'condition');
							xmlwriter_start_element($xml, 'field');
								xmlwriter_text ($xml, 'id');
								xmlwriter_start_element($xml, 'expression');
									xmlwriter_write_attribute ($xml, 'op', 'equals');
									xmlwriter_text ($xml, $contract_id);
								xmlwriter_end_element($xml);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
				xmlwriter_end_document ($xml);
				$xml = xmlwriter_output_memory ($xml);
				
				$params = array ('sXML' => $xml);
				
				try {
					// set_time_limit (30);
					$soapResponse = $soapClient->query($params);
					// print_r ($soapResponse); exit;
				} catch (SoapFault $e) {
					write_out ("ERROR: SOAP fault on Autotask account query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
				}
				
				$entities_a = array ();
				if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_a = $soapResponse->queryResult->EntityResults->Entity;
				if (!is_array ($entities_a)) $entities_a = array ($entities_a);
				
				if (empty ($entities_a)) write_out ("Contract ID #{$contract_id} referenced in {$at_file} not found in Autotask?!?", 1, 1, __FILE__, __LINE__);
				
				// match it if I got it!
				foreach ($entities_a as $er_a) {
					$v = (string) $er_a->ContractNumber;
					if (empty ($v)) continue;
					$contracts_matched[$contract_id] = $v;
				}
				
				// did I get it?
				if (isset ($contracts_matched[$contract_id])) {
					$data[$contract_no_field] = $contracts_matched[$contract_id];
				} else {
					// need this contract! Save the data for use later ...
					$contract_records++;
					$contract_data = array ();
					$contract_data[] = $contract_id;
					foreach ($data as $k => $v) {
						if (!in_array ($k, $contract_at_flds)) continue;
						if ($k == $acct_no_fld and empty ($v)) {
							$contract_data[] = $accts_matched[$data[$acct_id_fld]];
							continue;
						}
						$contract_data[] = $v;
					}
					$contracts_needed[$contract_id] = $contract_data;
					$incompletes++;
				}
			}
		} // end if need contract
		
		// set up clean csv for southware
		foreach ($exclude_at_flds as $v) unset ($data[$v]);
		foreach ($data as $k => $v) $data[$k] = '_BEGFLD_' . $v . '_ENDFLD_';
		$data[] = '_ENDLIN_';
		fputcsv ($fho, $data);
	} // end while csv data
	
	// close file handles
	fclose ($fhi);
	fclose ($fho);
	
	// how did I do?
	if ($incompletes == 0) {
		// I don't need any accounts from this file!
		// Clean the csv & Spin the dates!
		$file_contents = file_get_contents ($tmp_at_out);
		@unlink($tmp_at_out);
		// southware-ize the csv!
		$file_contents = str_replace (array ("\"_BEGFLD_", "_ENDFLD_\"", "_BEGFLD_", "_ENDFLD_", ",_ENDLIN_"),  array ("\"", "\"", "\"", "\"", "\r"), $file_contents);
		// spin the dates!
		$file_contents = preg_replace_callback("|,\"(\d{1,2})/(\d{1,2})/(\d{4})\",|", "sql_date", $file_contents);
		// twice to handle two dates in a row!
		$file_contents = preg_replace_callback("|,\"(\d{1,2})/(\d{1,2})/(\d{4})\",|", "sql_date", $file_contents);
		if (@file_put_contents ($ini['invoices_southware_dir'] . '/' . $at_file, $file_contents) === false) write_out ("ERROR: Unable to create file \"{$ini['invoices_southware_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
		// file written, now archive this file
		if (!@rename ($ini['invoices_autotask_dir'] . '/' . $at_file, $ini['invoices_autotask_archive_dir'] . '/' . $at_file)) write_out ("ERROR: Unable to move file \"{$at_file}\" into archive directory \"{$ini['invoices_autotask_archive_dir']}\"", 1, 1, __FILE__, __LINE__);
		write_out ("Successfully processed {$rows} invoice rows from {$at_file}\n... file forwarded to {$ini['invoices_southware_dir']}/{$at_file}\n... original archived!");
	} else {
		@unlink($tmp_at_out);
		write_out ("{$rows} invoice rows from {$at_file}\n... pending update of " . number_format($incompletes) . " line(s) with missing account and/or contract info\n... file retained for future processing!");
	}
} // end each autotask invoice file

// Do I need to push any accounts?
if (!empty ($accts_needed)) {

	// OK ... I need some accounts.
	// First see if they are already queued for import:
	$at_files = scandir ($ini['accounts_autotask_dir']);
	$queue_files = array ();
	foreach ($at_files as $at_file) {
		if (substr ($at_file, 0, 1) == '.') continue;
		if (!is_file ($ini['accounts_autotask_dir'] . '/' . $at_file)) continue;
		if (!$fhi = @fopen ($ini['accounts_autotask_dir'] . '/' . $at_file, 'r')) write_out ("ERROR: Unable to read \"{$ini['accounts_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
		while ( ($data = fgetcsv($fhi, 0, "\t") ) !== FALSE ) {
			if (isset ($accts_needed[$data[0]])) {
				unset ($accts_needed[$data[0]]);
				if (!in_array ($at_file, $queue_files)) $queue_files[] = $at_file;
			}
		}
		fclose ($fhi);
	}
	
	// were any already queued?
	if (!empty ($queue_files)) write_out ("Accounts queued for import in " . implode ("\nAccounts queued for import in ", $queue_files));
	
	// Do I need to push any accounts?
	if (!empty ($accts_needed)) {
		
		// WRITING ACCOUNTS
		// outfile
		if (!is_dir ($ini['accounts_autotask_dir'])) write_out ("ERROR: Cannot locate accounts_autotask_dir @ \"{$ini['accounts_autotask_dir']}\"", 1, 1, __FILE__, __LINE__);
		
		// create file in temp initially ...
		$account_export_file = 'AT_Accounts_' . date ('YmdHis') . '.tab';
		if (!$fho = @fopen (sys_get_temp_dir () . '/' . $account_export_file, 'w')) write_out ("ERROR: Unable to open file \"" . sys_get_temp_dir () . "/{$account_export_file}\"", 1, 1, __FILE__, __LINE__);
		// write headers?
		$headers = array ();
		foreach ($meta as $v) $headers[] = $v[0];
		// fwrite ($fho, implode ("\t", $headers) . "\n");
		
		$resource = array();
		$taxregion = array();
		$accounts = 0;
		foreach ($accts_needed as $acct_id => $acct_data) {
		
			write_out ('Exporting ' . $acct_data['AccountName']);
			
			// contacts! ... only setting if 1 active contact!
			$xml = xmlwriter_open_memory();
			xmlwriter_start_document ($xml);
			xmlwriter_start_element($xml, 'queryxml');
				xmlwriter_start_element($xml, 'entity');
					xmlwriter_text ($xml, 'Contact');
				xmlwriter_end_element($xml);
				xmlwriter_start_element($xml, 'query');
					xmlwriter_start_element($xml, 'condition');
						xmlwriter_start_element($xml, 'field');
							xmlwriter_text ($xml, 'AccountID');
							xmlwriter_start_element($xml, 'expression');
								xmlwriter_write_attribute ($xml, 'op', 'equals');
								xmlwriter_text ($xml, $acct_id);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
					xmlwriter_start_element($xml, 'condition');
						xmlwriter_start_element($xml, 'field');
							xmlwriter_text ($xml, 'Active');
							xmlwriter_start_element($xml, 'expression');
								xmlwriter_write_attribute ($xml, 'op', 'equals');
								xmlwriter_text ($xml, 1);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
			xmlwriter_end_element($xml);
			xmlwriter_end_document ($xml);
			$xml = xmlwriter_output_memory ($xml);
			
			$params = array ('sXML' => $xml);
			
			try {
				// set_time_limit (30);
				$soapResponse = $soapClient->query($params);
				// print_r ($soapResponse); exit;
			} catch (SoapFault $e) {
				write_out ("ERROR: SOAP fault on Autotask contact query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
			}
			
			$entities_c = array ();
			if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_c = $soapResponse->queryResult->EntityResults->Entity;
			if (!is_array ($entities_c)) $entities_c = array ($entities_c);
			
			// if there is one active contact, set it as primary, otherwise bail out!
			$contact = array('name' => '', 'email' => '');
			foreach ($entities_c as $er_c) {
				if (!empty ($contact['name'])) {
					$contact = array('name' => '', 'email' => '');
					break;
				}
				$contact['name'] = (string) $er_c->LastName . ', ' . (string) $er_c->FirstName;
				if (isset ($er_c->EMailAddress)) $contact['email'] .= (string) $er_c->EMailAddress;		
			}
				
			// owner ... might have already grabbed it!
			if(!isset($resource[$acct_data['OwnerResourceID']])) {
			
				$resource[$acct_data['OwnerResourceID']] = array (
					'name' => '',
					'initials' => '',
					);
			
				$xml = xmlwriter_open_memory();
				xmlwriter_start_document ($xml);
				xmlwriter_start_element($xml, 'queryxml');
					xmlwriter_start_element($xml, 'entity');
						xmlwriter_text ($xml, 'Resource');
					xmlwriter_end_element($xml);
					xmlwriter_start_element($xml, 'query');
						xmlwriter_start_element($xml, 'condition');
							xmlwriter_start_element($xml, 'field');
								xmlwriter_text ($xml, 'id');
								xmlwriter_start_element($xml, 'expression');
									xmlwriter_write_attribute ($xml, 'op', 'equals');
									xmlwriter_text ($xml, $acct_data['OwnerResourceID']);
								xmlwriter_end_element($xml);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
				xmlwriter_end_document ($xml);
				$xml = xmlwriter_output_memory ($xml);
				
				$params = array ('sXML' => $xml);
				
				try {
					// set_time_limit (30);
					$soapResponse = $soapClient->query($params);
					// print_r ($soapResponse); exit;
				} catch (SoapFault $e) {
					write_out ("ERROR: SOAP fault on Autotask resource query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
				}
				
				$entities_o = array ();
				if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_o = $soapResponse->queryResult->EntityResults->Entity;
				if (!is_array ($entities_o)) $entities_o = array ($entities_o);
				
				foreach ($entities_o as $er_o) {
					$resource[$acct_data['OwnerResourceID']]['name'] = (string) $er_o->LastName . ', ' . (string) $er_o->FirstName;
					if (isset ($er_o->Initials)) $resource[$acct_data['OwnerResourceID']]['initials'] .= (string) $er_o->Initials;
					break;
				}
				
			} // end if resource doesn't exist
			
			// tax region ... might have already grabbed it!
			if(!empty ($acct_data['TaxRegionID']) and !isset($taxregion[$acct_data['TaxRegionID']])) {
			
				$taxregion[$acct_data['TaxRegionID']] = array (
					'name' => '',
					);
			
				$xml = xmlwriter_open_memory();
				xmlwriter_start_document ($xml);
				xmlwriter_start_element($xml, 'queryxml');
					xmlwriter_start_element($xml, 'entity');
						xmlwriter_text ($xml, 'TaxRegion');
					xmlwriter_end_element($xml);
					xmlwriter_start_element($xml, 'query');
						xmlwriter_start_element($xml, 'condition');
							xmlwriter_start_element($xml, 'field');
								xmlwriter_text ($xml, 'id');
								xmlwriter_start_element($xml, 'expression');
									xmlwriter_write_attribute ($xml, 'op', 'equals');
									xmlwriter_text ($xml, $acct_data['TaxRegionID']);
								xmlwriter_end_element($xml);
							xmlwriter_end_element($xml);
						xmlwriter_end_element($xml);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
				xmlwriter_end_document ($xml);
				$xml = xmlwriter_output_memory ($xml);
				
				$params = array ('sXML' => $xml);
				
				try {
					// set_time_limit (30);
					$soapResponse = $soapClient->query($params);
					// print_r ($soapResponse); exit;
				} catch (SoapFault $e) {
					write_out ("ERROR: SOAP fault on Autotask resource query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
				}
				
				$entities_tr = array ();
				if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_tr = $soapResponse->queryResult->EntityResults->Entity;
				if (!is_array ($entities_tr)) $entities_tr = array ($entities_tr);
				
				foreach ($entities_tr as $er_tr) {
					$taxregion[$acct_data['TaxRegionID']]['name'] = (string) $er_tr->Name;
					break;
				}
				
			} // end if tax region doesn't exist
			
			$data = array ();
			foreach ($meta as $v) {
				$fld = '';
				switch ($v[1]) {
					case 'account':
						if (isset ($acct_data[$v[2]])) $fld = $acct_data[$v[2]];
						break;
					case 'contact':
						$fld = $contact[$v[2]];
						break;
					case 'owner':
						$fld = $resource[$acct_data['OwnerResourceID']][$v[2]];
						break;
					case 'taxregion':
						if (!empty ($acct_data['TaxRegionID'])) $fld = $taxregion[$acct_data['TaxRegionID']][$v[2]];
						break;
					case 'literal':
						$fld = $v[2];
						break;
					case 'calc':
						eval ('$fld = ' . $v[2] . ';');
						break;
					default:
						write_out ("ERROR: Unknown context {$v[1]}", 1, 1, __FILE__, __LINE__);
				}
				$data[] = data_clean($fld);
			}
			fwrite ($fho, implode ("\t", $data) . "\n");
			$accounts++;
		}
		
		fclose ($fho);
		if (!@rename (sys_get_temp_dir () . '/' . $account_export_file, $ini['accounts_autotask_dir'] . '/' . $account_export_file)) write_out ("Unable to archive/move {$account_export_file} to {$ini['accounts_autotask_dir']}", 1, 1, __FILE__, __LINE__);
		write_out ("SUCCESS: {$accounts} account(s) written to {$ini['accounts_autotask_dir']}/{$account_export_file}");
				
	} // end if some accounts not already queued
} // end if accounts needed!

// Do I need to push any contracts?
if (!empty ($contracts_needed)) {

	// OK ... I need some contracts.
	// First see if they are already queued for import:
	$at_files = scandir ($ini['contracts_autotask_dir']);
	$queue_files = array ();
	foreach ($at_files as $at_file) {
		if (substr ($at_file, 0, 1) == '.') continue;
		if (!is_file ($ini['contracts_autotask_dir'] . '/' . $at_file)) continue;
		if (!$fhi = @fopen ($ini['contracts_autotask_dir'] . '/' . $at_file, 'r')) write_out ("ERROR: Unable to read \"{$ini['contracts_autotask_dir']}/{$at_file}\"", 1, 1, __FILE__, __LINE__);
		while ( ($data = fgetcsv($fhi) ) !== FALSE ) {
			if (isset ($contracts_needed[$data[0]])) {
				unset ($contracts_needed[$data[0]]);
				if (!in_array ($at_file, $queue_files)) $queue_files[] = $at_file;
			}
		}
		fclose ($fhi);
	}
	
	// were any already queued?
	if (!empty ($queue_files)) write_out ("Contracts queued for import in " . implode ("\nContracts queued for import in ", $queue_files));
	
	// Do I need to push any contracts?
	if (!empty ($contracts_needed)) {
		
		// WRITING CONTRACTS
		// outfile
		if (!is_dir ($ini['contracts_autotask_dir'])) write_out ("ERROR: Cannot locate contracts_autotask_dir @ \"{$ini['contracts_autotask_dir']}\"", 1, 1, __FILE__, __LINE__);
		
		// create file in temp initially ...
		$contract_export_file = 'AT_Contracts_' . date ('YmdHis') . '.tab';
		if (!$fho = @fopen (sys_get_temp_dir () . '/' . $contract_export_file, 'w')) write_out ("ERROR: Unable to open file \"" . sys_get_temp_dir () . "/{$contract_export_file}\"", 1, 1, __FILE__, __LINE__);

		$contracts = 0;
		foreach ($contracts_needed as $contract_id => $contract_data) {
			foreach ($contract_data as $k => $v) $contract_data[$k] = '_BEGFLD_' . $v . '_ENDFLD_';
			$contract_data[] = '_ENDLIN_';
			fputcsv ($fho, $contract_data);
			$contracts++;
		}
		
		fclose ($fho);

		$file_contents = file_get_contents (sys_get_temp_dir () . '/' . $contract_export_file);
		@unlink(sys_get_temp_dir () . '/' . $contract_export_file);
		// southware-ize the csv!
		$file_contents = str_replace (array ("\"_BEGFLD_", "_ENDFLD_\"", "_BEGFLD_", "_ENDFLD_", ",_ENDLIN_"),  array ("\"", "\"", "\"", "\"", "\r"), $file_contents);
		// spin the dates!
		$file_contents = preg_replace_callback("|,\"(\d{1,2})/(\d{1,2})/(\d{4})\",|", "sql_date", $file_contents);
		// twice to handle two dates in a row!
		$file_contents = preg_replace_callback("|,\"(\d{1,2})/(\d{1,2})/(\d{4})\",|", "sql_date", $file_contents);
		if (@file_put_contents ($ini['contracts_autotask_dir'] . '/' . $contract_export_file, $file_contents) === false) write_out ("ERROR: Unable to create file \"{$ini['contracts_autotask_dir']}/{$contract_export_file}\"", 1, 1, __FILE__, __LINE__);
		write_out ("SUCCESS: {$contracts} contracts(s) written to {$ini['contracts_autotask_dir']}/{$contract_export_file}");
				
	} // end if some contracts not already queued
} // end if contracts needed!

write_out ('DONE!', 0, 1);

function data_clean ($d) {
	$d = trim ($d);
	$d = str_replace (array ("\t", "\n", "\r"), array ('', ' ', ''), $d);
	$d = preg_replace ('/[^(\x20-\x7F)]*/', '', $d);
	return $d;
}

function get_task_ticket ($id, $entity) {
	global $soapClient, $at_file, $tasks_matched, $tickets_matched;
	
	if ($entity == 'Task' and isset ($tasks_matched[$id])) return $tasks_matched[$id];
	if ($entity == 'Ticket' and isset ($tickets_matched[$id])) return $tickets_matched[$id];
	
	// have to go get it!
	write_out ("Querying Autotask for {$entity} #{$id} ...");
	$xml = xmlwriter_open_memory();
	xmlwriter_start_document ($xml);
	xmlwriter_start_element($xml, 'queryxml');
		xmlwriter_start_element($xml, 'entity');
			xmlwriter_text ($xml, $entity);
		xmlwriter_end_element($xml);
		xmlwriter_start_element($xml, 'query');
			xmlwriter_start_element($xml, 'condition');
				xmlwriter_start_element($xml, 'field');
					xmlwriter_text ($xml, 'id');
					xmlwriter_start_element($xml, 'expression');
						xmlwriter_write_attribute ($xml, 'op', 'equals');
						xmlwriter_text ($xml, $id);
					xmlwriter_end_element($xml);
				xmlwriter_end_element($xml);
			xmlwriter_end_element($xml);
		xmlwriter_end_element($xml);
	xmlwriter_end_element($xml);
	xmlwriter_end_document ($xml);
	$xml = xmlwriter_output_memory ($xml);
	
	$params = array ('sXML' => $xml);
	
	try {
		// set_time_limit (30);
		$soapResponse = $soapClient->query($params);
		// print_r ($soapResponse); exit;
	} catch (SoapFault $e) {
		write_out ("ERROR: SOAP fault on Autotask account query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
	}
	
	$entities_a = array ();
	if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_a = $soapResponse->queryResult->EntityResults->Entity;
	if (!is_array ($entities_a)) $entities_a = array ($entities_a);
	
	if (empty ($entities_a)) write_out ("{$entity} #{$id} referenced in {$at_file} not found in Autotask?!?", 1, 1, __FILE__, __LINE__);
	
	// match it if I got it!
	foreach ($entities_a as $er_a) {
		if ($entity == 'Task') {
			$v = (string) $er_a->TaskNumber;
			$tasks_matched[$id] = $v;
			return $v;
		}
		$v = (string) $er_a->TicketNumber;
		$tickets_matched[$id] = $v;
		return $v;
	}
}

function write_out ($message, $error = 0, $fatal = 0, $file = '', $line = '') {
	global $ini, $argv;
	
	if (!isset ($GLOBALS['exec_results'])) $GLOBALS['exec_results'] = array ();
	if (!isset ($GLOBALS['exec_errors'])) $GLOBALS['exec_errors'] = array ();
	if (!empty ($error)) {
		if (!empty ($file)) $message .= " ({$file} Line {$line})";
		$GLOBALS['exec_errors'][] = $message;
	}
	
	if (!empty ($message)) {
		echo $message . "\n";
		$GLOBALS['exec_results'][] = $message;
	}
	
	if (empty ($fatal)) return;
	
	// notifications!?!
	if (isset ($ini['notify_errors']) and !empty ($GLOBALS['exec_errors'])) {
		echo "===========================================================================\n";
		if (validate_email_addresses ($ini['notify_errors'])) {
			$message = "Errors were encountered while executing the following script:\n";
			$message .= implode (' ', $argv) . "\n\n";
			$message .= implode ("\n", $GLOBALS['exec_errors']);
			$sent = @mail ($ini['notify_errors'], 'CFS Autotask / Southware Script Results - WITH ERRORS', $message);
			if ($sent) {
				echo "Error notification sent to {$ini['notify_errors']}\n";
			} else {
				echo "Unable to send error notification to {$ini['notify_errors']}\n";
			}
		} else {
			echo "Cannot understand the notify_errors configuration directive: \"{$ini['notify_errors']}\"\n";		
		}
	}

	// notifications!?!
	if (isset ($ini['notify_results'])) {
		echo "===========================================================================\n";
		if (validate_email_addresses ($ini['notify_results'])) {
			$message = "Results were reported while executing the following script:\n";
			$message .= implode (' ', $argv) . "\n\n";
			$message .= implode ("\n", $GLOBALS['exec_results']) . "\n";
			$message .= "===========================================================================\n";
			$message .= "Run Time = " . number_format (time() - START_TIME) . " seconds\n";
			$message .= number_format (count ($GLOBALS['exec_errors'])) . " errors\n";
			$message .= "DONE\n";
			$sent = @mail ($ini['notify_results'], 'CFS Autotask / Southware Script Results', $message);
			if ($sent) {
				echo "Results notification sent to {$ini['notify_results']}\n";
			} else {
				echo "Unable to send results notification to {$ini['notify_results']}\n";
			}
		} else {
			echo "Cannot understand the notify_errors configuration directive: \"{$ini['notify_results']}\"\n";		
		}
	}

	echo "===========================================================================\n";
	echo "Run Time = " . number_format (time() - START_TIME) . " seconds\n";
	echo number_format (count ($GLOBALS['exec_errors'])) . " error(s)\n";
	echo "DONE\n";
	exit;

}

function sql_date ($matches) {
	$date = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
	$date = strtotime ($date);
	if (empty ($date)) return $matches[0];
	return ',' . date ('m/d/y', $date) . ',';	
}

function validate_email_addresses ($adds) {
	global $ini;
	if (empty ($adds)) return false;
	if (empty ($ini['smtp_host']) or empty ($ini['smtp_port']) or empty ($ini['smtp_from'])) {
		write_out ("Unable to send mail ... SMTP Notification directives not set in *.ini file! Notifications not sent!", 1);
		return false;
	}
	if (!function_exists ('imap_rfc822_parse_adrlist')) {
		write_out ("Unable to parse email addresses. IMAP library must be enabled! Notifications not sent!", 1);
		return false;
	}
	$addresses = imap_rfc822_parse_adrlist ($adds, 'bogus.com');
	if (!is_array($addresses) or count($addresses) < 1) {
		write_out ("\"{$adds}\" does not contain valid e-mail address(es). Notifications not sent.", 1);
		return false;
	}
	foreach ($addresses as $address) {
		if ((string) $address->host == 'bogus.com') {
			write_out ("\"{$adds}\" does not contain valid e-mail address(es). Notifications not sent.", 1);
			return false;
		}
	}
	return true;
}

function help_txt() {
	return <<<EOD

Usage Example:
    PROMPT>php cfs_at_sw.php

OPTIONAL COMMAND LINE PARAMETERS
ini - Path to an ini file
    Usage: ini=foo.ini
    Usage: ini=c:/temp/foo.ini
    Usage: ini="c:/dir name with spaces/file name with spaces.ini"
test_mail - sends test message to the notify_results email list
    Usage: test_mail
test_error_mail - sends test message to the notify_errors email list
    Usage: test_error_mail
version - returns current program version
    Usage: version

Note: if no ini file specified, any ini file in the same directory and having the same base name as this script will be used!

EOD;
}

?>
