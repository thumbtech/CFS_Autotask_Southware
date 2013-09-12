<?php
// cfs_at_sw_quotes.php
// version 0.9.2 09/12/2013
// GreenPages Technology Solutions, Inc.
define ('VERSION', '0.9.2 09/12/2013');

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
	'quoteid',
	'quotenumber',
	'quotename',
	);

$missing_cli_params = array ();	
foreach($required_cli_params as $v) if(!isset($_REQUEST[$v])) $missing_cli_params[] = $v;
if(count ($missing_cli_params) >= count ($required_cli_params))
	write_out ("ERROR: Missing at least one of the reqired command line parameter(s) \"" . implode(', ', $missing_cli_params) . "\"\n\n" . help_txt(), 1, 1, __FILE__, __LINE__);
//print_r ($_REQUEST); exit;

// did user specify an ini file? ... if not use default
if (!isset($_REQUEST['ini'])) $_REQUEST['ini'] = str_replace ('_quotes', '', basename(__FILE__, 'php')) . 'ini';

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
	'accounts_southware_dir',
	'accounts_southware_archive_dir',
	'accounts_autotask_dir',
	'quotes_autotask_dir',
	);

$missing_ini_vars = array ();	
foreach($required_ini_vars as $v) if(!isset($ini[$v])) $missing_ini_vars[] = $v;
if(!empty($missing_ini_vars))
	write_out ("ERROR: Missing ini directive(s) \"" . implode(', ', $missing_ini_vars) . "\" in {$_REQUEST['ini']}", 1, 1, __FILE__, __LINE__);
//print_r ($ini); exit;

// now we can get to work ...
// set the autotask wsdl for the zone
$ini['autotask_api_wsdl'] = "https://webservices{$ini['autotask_api_zone']}.autotask.net/atservices/1.5/atws.wsdl";

// headers arrays of name, context, value for accounts
$meta_account = array (
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
	);

// headers arrays of name, context, value for quotes
$meta_quote = array (
	);

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

// any quotes to process?
$xml = xmlwriter_open_memory();
xmlwriter_start_document ($xml);
xmlwriter_start_element($xml, 'queryxml');
	xmlwriter_start_element($xml, 'entity');
		xmlwriter_text ($xml, 'Quote');
	xmlwriter_end_element($xml);
	xmlwriter_start_element($xml, 'query');
		xmlwriter_start_element($xml, 'condition');
			xmlwriter_start_element($xml, 'field');
if (!empty ($_REQUEST['quoteid'])) {
				xmlwriter_text ($xml, 'id');
				xmlwriter_start_element($xml, 'expression');
					xmlwriter_write_attribute ($xml, 'op', 'equals');
					xmlwriter_text ($xml, $_REQUEST['quoteid']);
				xmlwriter_end_element($xml);
} elseif (!empty ($_REQUEST['quotenumber'])) {
				xmlwriter_text ($xml, 'ExternalQuoteNumber');
				xmlwriter_start_element($xml, 'expression');
					xmlwriter_write_attribute ($xml, 'op', 'equals');
					xmlwriter_text ($xml, $_REQUEST['quotenumber']);
				xmlwriter_end_element($xml);
} elseif (!empty ($_REQUEST['quotename'])) {
				xmlwriter_text ($xml, 'Name');
				xmlwriter_start_element($xml, 'expression');
					xmlwriter_write_attribute ($xml, 'op', 'equals');
					xmlwriter_text ($xml, $_REQUEST['quotename']);
				xmlwriter_end_element($xml);
}
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
	write_out ("ERROR: SOAP fault on Autotask quote query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
}

$entities_q = array ();
if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_q = $soapResponse->queryResult->EntityResults->Entity;
if (!is_array ($entities_q)) $entities_q = array ($entities_q);
//print_r ($entities_q); exit;

// found quotes get 'em?
$resource = array(); // for the owner
$vendor = array(); // for the product vendor
$location = array(); // for the addresses
$incompletes = 0;
foreach ($entities_q as $er_q) {

	write_out ("Exporting Quote #" . (int) $er_q->id . " - " . (string) $er_q->Name);
	
	// need to get the Southware account number
	$account = (int) $er_q->AccountID;
	if (isset ($accts_matched[$account])) {
		$account = $accts_matched[$account];
	} elseif (isset ($accts_needed[$account])) {
		// OK I already know I'm going to need to get this one
		write_out ("     Export skipped pending Southware account import");
		$incompletes++;
		continue;
	} else {
		// find account and see if it has an AccountNumber
		write_out ("Querying Autotask for account {$account} ...");
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
							xmlwriter_text ($xml, $account);
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
		
		if (empty ($entities_a)) write_out ("Account ID #{$account} referenced in Quote #" . (int) $er_q->id . " not found in Autotask?!?", 1, 1, __FILE__, __LINE__);
		
		// match it if I got it!
		foreach ($entities_a as $er_a) {
			$v = (string) $er_a->AccountNumber;
			if (empty ($v)) continue;
			$accts_matched[$account] = $v;
		}
		
		// did I get it?
		if (isset ($accts_matched[$account])) {
			$account = $accts_matched[$account];
		} else {
			// need this account! Save the data for use later ...
			$acct_data = array ();
			foreach ($meta_account as $v) {
				switch ($v[1]) {
					case 'account':
						if (isset ($er_a->$v[2])) $acct_data[$v[2]] = (string) $er_a->$v[2];
						break;
				}
			}					
			$accts_needed[$account] = $acct_data;
			write_out ("     Export skipped pending Southware account import");
			$incompletes++;
			continue;
		}
	}

	// OK, I have the account!
	// need to get the opp for the owner ...
	$owner = array (
		'id' => '',
		'name' => '',
		'initials' => '',
		);
	
	$xml = xmlwriter_open_memory();
	xmlwriter_start_document ($xml);
	xmlwriter_start_element($xml, 'queryxml');
		xmlwriter_start_element($xml, 'entity');
			xmlwriter_text ($xml, 'Opportunity');
		xmlwriter_end_element($xml);
		xmlwriter_start_element($xml, 'query');
			xmlwriter_start_element($xml, 'condition');
				xmlwriter_start_element($xml, 'field');
					xmlwriter_text ($xml, 'id');
					xmlwriter_start_element($xml, 'expression');
						xmlwriter_write_attribute ($xml, 'op', 'equals');
						xmlwriter_text ($xml, (int) $er_q->OpportunityID);
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
		write_out ("ERROR: SOAP fault on Autotask opportunity query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
	}
	
	$entities_o = array ();
	if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_o = $soapResponse->queryResult->EntityResults->Entity;
	if (!is_array ($entities_o)) $entities_o = array ($entities_o);
	// print_r ($entities_o); exit;
	
	foreach ($entities_o as $er_o) {
		$owner['id'] = (int) $er_o->OwnerResourceID;
		// get the resource info, if needed
		if(!isset($resource[$owner['id']])) {
		
			$resource[$owner['id']] = array (
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
								xmlwriter_text ($xml, $owner['id']);
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
			
			$entities_r = array ();
			if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_r = $soapResponse->queryResult->EntityResults->Entity;
			if (!is_array ($entities_r)) $entities_r = array ($entities_r);
			
			foreach ($entities_r as $er_r) {
				$resource[$owner['id']]['name'] = (string) $er_r->LastName . ', ' . (string) $er_r->FirstName;
				if (isset ($er_r->Initials)) $resource[$owner['id']]['initials'] .= (string) $er_r->Initials;
				break;
			}
			
		} // end if resource doesn't exist
		
		// set owner info
		$owner['name'] = $resource[$owner['id']]['name'];
		$owner['initials'] = $resource[$owner['id']]['initials'];
		break;
	}
	
	// location info
	$sl = (int) $er_q->ShipToLocationID;
	if (!isset ($location[$sl])) $location[$sl] = get_location ($sl);
	$bl = (int) $er_q->BillToLocationID;
	if (!isset ($location[$bl])) $location[$bl] = get_location ($bl);
	
	$header = array (
		'QuoteID' => (int) $er_q->id,
		'ExternalQuoteNumber' => '',
		'QuoteName' => (string) $er_q->Name,
		'AccountID' => $account,
		'OwnerID' => $owner['id'],
		'OwnerName' => $owner['name'],
		'OwnerInitials' => $owner['initials'],
		'PurchaseOrderNumber' => '',
		'ContactID' => (int) $er_q->ContactID,
		'CreateDate' => date ('m/d/y', strtotime (substr((string) $er_q->CreateDate, 0, 10))),
		'Description' => '',
		'ShipToLocationID' => $sl,
		'ShipToAddress1' => $location[$sl]['Address1'],
		'ShipToAddress2' => $location[$sl]['Address2'],
		'ShipToCity' => $location[$sl]['City'],
		'ShipToState' => $location[$sl]['State'],
		'ShipToPostalCode' => $location[$sl]['PostalCode'],
		'BillToLocationID' => $bl,
		'BillToAddress1' => $location[$bl]['Address1'],
		'BillToAddress2' => $location[$bl]['Address2'],
		'BillToCity' => $location[$bl]['City'],
		'BillToState' => $location[$bl]['State'],
		'BillToPostalCode' => $location[$bl]['PostalCode'],
		);
		
	if (!empty ($er_q->ExternalQuoteNumber)) $header['ExternalQuoteNumber'] = (string) $er_q->ExternalQuoteNumber;
	if (!empty ($er_q->PurchaseOrderNumber)) $header['PurchaseOrderNumber'] = (string) $er_q->PurchaseOrderNumber;
	if (!empty ($er_q->Description)) $header['Description'] = (string) $er_q->Description;
	
	// Get locations
	
	// now get the quote lines!
	$xml = xmlwriter_open_memory();
	xmlwriter_start_document ($xml);
	xmlwriter_start_element($xml, 'queryxml');
		xmlwriter_start_element($xml, 'entity');
			xmlwriter_text ($xml, 'QuoteItem');
		xmlwriter_end_element($xml);
		xmlwriter_start_element($xml, 'query');
			xmlwriter_start_element($xml, 'condition');
				xmlwriter_start_element($xml, 'field');
					xmlwriter_text ($xml, 'QuoteID');
					xmlwriter_start_element($xml, 'expression');
						xmlwriter_write_attribute ($xml, 'op', 'equals');
						xmlwriter_text ($xml, (int) $er_q->id);
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
		write_out ("ERROR: SOAP fault on Autotask quote item query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
	}
	
	$entities_i = array ();
	if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_i = $soapResponse->queryResult->EntityResults->Entity;
	if (!is_array ($entities_i)) $entities_i = array ($entities_i);
	
	foreach ($entities_i as $er_i) {
		// print_r ($er_i); exit;
		$line = array (
			'Type' => (int) $er_i->Type,
			'ItemDescription1' => '',
			'ItemDescription2' => '',
			'ItemDescription3' => '',
			'ItemSKU' => '',
			'ItemManufacturerName' => '',
			'ItemManufacturerProductName' => '',
			'ItemVendorID' => '',
			'ItemVendorName' => '',
			'ItemVendorProductNumber' => '',
			'ItemExternalProductID' => '',
			'ItemQty' => (float) $er_i->Quantity,
			'ItemUnitCost' => 0,
			'ItemUnitPrice' => 0,
			'ItemMSRP' => 0,
			'ItemLineDiscount' => 0,
			'ItemUnitDiscount' => 0,
			'ItemPercentageDiscount' => 0,
			);
			
		if (!empty ($er_i->Name)) $line['ItemDescription1'] = (string) $er_i->Name;	
		if (!empty ($er_i->Description)) $line['ItemDescription2'] = (string) $er_i->Description;	
		if (!empty ($er_i->UnitCost)) $line['ItemUnitCost'] = (float) $er_i->UnitCost;	
		if (!empty ($er_i->UnitPrice)) $line['ItemUnitPrice'] = (float) $er_i->UnitPrice;	
		if (!empty ($er_i->LineDiscount)) $line['ItemLineDiscount'] = (float) $er_i->LineDiscount;	
		if (!empty ($er_i->UnitDiscount)) $line['ItemUnitDiscount'] = (float) $er_i->UnitDiscount;	
		if (!empty ($er_i->PercentageDiscount)) $line['ItemPercentageDiscount'] = (float) $er_i->PercentageDiscount;
		
		// Some odd clean up to do here ...
		if ($line['ItemDescription1'] == '') {
			$line['ItemDescription1'] = $line['ItemDescription2'];
			$line['ItemDescription2'] = '';
		}
		
		if ($line['ItemDescription1'] != '') {
			// this seems to be Quosals habit:
			if (strstr ($line['ItemDescription2'], $line['ItemDescription1']) !== false) {
				$line['ItemDescription1'] = $line['ItemDescription2'];
				$line['ItemDescription2'] = '';
			}
		}
		
		// Southware has three 30-char fields!	
		if (strlen ($line['ItemDescription1']) > 30) {
			if ($line['ItemDescription2'] != '') $line['ItemDescription2'] = ' - ' . $line['ItemDescription2'];
			$line['ItemDescription2'] = substr ($line['ItemDescription1'], 30) . $line['ItemDescription2'];
			$line['ItemDescription1'] = substr ($line['ItemDescription1'], 0, 30);
		}
		
		if (strlen ($line['ItemDescription2']) > 30) {
			$line['ItemDescription3'] = substr ($line['ItemDescription2'], 30, 60);
			$line['ItemDescription2'] = substr ($line['ItemDescription2'], 0, 30);
		}

			
		// Product to get?
		if ((int) $er_i->Type == 1) {
			// now get the product lines!
			$xml = xmlwriter_open_memory();
			xmlwriter_start_document ($xml);
			xmlwriter_start_element($xml, 'queryxml');
				xmlwriter_start_element($xml, 'entity');
					xmlwriter_text ($xml, 'Product');
				xmlwriter_end_element($xml);
				xmlwriter_start_element($xml, 'query');
					xmlwriter_start_element($xml, 'condition');
						xmlwriter_start_element($xml, 'field');
							xmlwriter_text ($xml, 'id');
							xmlwriter_start_element($xml, 'expression');
								xmlwriter_write_attribute ($xml, 'op', 'equals');
								xmlwriter_text ($xml, (int) $er_i->ProductID);
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
				write_out ("ERROR: SOAP fault on Autotask quote item query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
			}
			
			$entities_p = array ();
			if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_p = $soapResponse->queryResult->EntityResults->Entity;
			if (!is_array ($entities_p)) $entities_p = array ($entities_p);
			
			foreach ($entities_p as $er_p) {
				// print_r ($er_p); exit;
				if (!empty ($er_p->SKU)) $line['ItemSKU'] = (string) $er_p->SKU;	
				if (!empty ($er_p->ExternalProductID)) $line['ItemExternalProductID'] = (string) $er_p->ExternalProductID;	
				if (!empty ($er_p->ManufacturerName)) $line['ItemManufacturerName'] = (string) $er_p->ManufacturerName;	
				if (!empty ($er_p->ManufacturerProductName)) $line['ItemManufacturerProductName'] = (string) $er_p->ManufacturerProductName;	
				if (!empty ($er_p->DefaultVendorID)) $line['ItemVendorID'] = (string) $er_p->DefaultVendorID;	
				if (!empty ($er_p->VendorProductNumber)) $line['ItemVendorProductNumber'] = (string) $er_p->VendorProductNumber;	
				if (!empty ($er_p->MSRP)) $line['ItemMSRP'] = (string) $er_p->MSRP;	
				break;
			}
		} // end if product
		
		// need the vendor?
		if (!empty ($line['ItemVendorID'])) {
			// get the vendor info ... if needed!
			if (!isset ($vendor[$line['ItemVendorID']])) {
				$vendor[$line['ItemVendorID']] = array (
					'name' => '',
					);
			
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
									xmlwriter_text ($xml, $line['ItemVendorID']);
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
					write_out ("ERROR: SOAP fault on Autotask account (vendor) query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
				}
				
				$entities_v = array ();
				if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities_v = $soapResponse->queryResult->EntityResults->Entity;
				if (!is_array ($entities_v)) $entities_v = array ($entities_v);
				
				foreach ($entities_v as $er_v) {
					$vendor[$line['ItemVendorID']]['name'] = (string) $er_v->AccountName;
					break;
				}
			}		
			
			// can set it now!
			$line['ItemVendorName'] = $vendor[$line['ItemVendorID']]['name'];
		}
		
		// OK ready to write a line!
		// need the file? ...
		if (empty ($fho)) {
			if (!is_dir ($ini['quotes_autotask_dir'])) write_out ("ERROR: Cannot locate quotes_autotask_dir @ \"{$ini['quotes_autotask_dir']}\"", 1, 1, __FILE__, __LINE__);
		
			// create file in temp initially ...
			$quote_export_file = 'AT_Quotes_' . date ('YmdHis') . '.csv';
			if (!$fho = @fopen (sys_get_temp_dir () . '/' . $quote_export_file, 'w')) write_out ("ERROR: Unable to open file \"" . sys_get_temp_dir () . "/{$quote_export_file}\"", 1, 1, __FILE__, __LINE__);

			// write the headers
			$data = array_merge (array_keys ($header), array_keys ($line));
			foreach ($data as $k => $v) $data[$k] = '_BEGFLD_' . data_clean ($v) . '_ENDFLD_';
			$data[] = '_ENDLIN_';
			fputcsv ($fho, $data);
		}
		
		$data = array_merge ($header, $line);
		foreach ($data as $k => $v) $data[$k] = '_BEGFLD_' . data_clean ($v) . '_ENDFLD_';
		$data[] = '_ENDLIN_';
		fputcsv ($fho, $data);
		
	} // end each line
			
} // end each quote

// Did I write any?
if (!empty ($fho)) {
	fclose ($fho);
	$file_contents = file_get_contents (sys_get_temp_dir () . '/' . $quote_export_file);
	@unlink(sys_get_temp_dir () . '/' . $quote_export_file);
	// southware-ize the csv!
	$file_contents = str_replace (array ("\"_BEGFLD_", "_ENDFLD_\"", "_BEGFLD_", "_ENDFLD_", ",_ENDLIN_"),  array ("\"", "\"", "\"", "\"", "\r"), $file_contents);
	if (@file_put_contents ($ini['quotes_autotask_dir'] . '/' . $quote_export_file, $file_contents) === false) write_out ("ERROR: Unable to create file \"{$ini['quotes_autotask_dir']}/{$quote_export_file}\"", 1, 1, __FILE__, __LINE__);
	write_out ("SUCCESS: Quotes(s) written to {$ini['quotes_autotask_dir']}/{$quote_export_file}");
} else {
	write_out ("No quote(s) exported.");
}

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
		foreach ($meta_account as $v) $headers[] = $v[0];
		// fwrite ($fho, implode ("\t", $headers) . "\n");
		
		$resource = array();
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
			
			$data = array ();
			foreach ($meta_account as $v) {
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

write_out ('DONE!', 0, 1);

function data_clean ($d) {
	$d = trim ($d);
	$d = str_replace (array ("\t", "\n", "\r"), array ('', ' ', ''), $d);
	return $d;
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

function get_location ($id) {
	global $soapClient;
	
	$return = array (
		'Address1' => '',
		'Address2' => '',
		'City' => '',
		'State' => '',
		'PostalCode' => '',
		);
		
	$xml = xmlwriter_open_memory();
	xmlwriter_start_document ($xml);
	xmlwriter_start_element($xml, 'queryxml');
		xmlwriter_start_element($xml, 'entity');
			xmlwriter_text ($xml, 'QuoteLocation');
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
		write_out ("ERROR: SOAP fault on Autotask quote location query: " . $e->faultstring, 1, 1, __FILE__, __LINE__);
	}
	
	$entities = array ();
	if (isset ($soapResponse->queryResult->EntityResults->Entity)) $entities = $soapResponse->queryResult->EntityResults->Entity;
	if (!is_array ($entities)) $entities = array ($entities);
	
	foreach ($entities as $er) {
		foreach ($return as $k => $v) {
			if (!empty ($er->$k)) $return[$k] = (string) $er->$k;
		}
		break;
	}
	
	return $return;
} // end get_location

function help_txt() {
	return <<<EOD

Usage Example:
    PROMPT>php cfs_at_sw_quotes.php
    
REQUIRED COMMAND LINE PARAMETERS (one of the following):
quoteid - Autotask quote id to export
    Usage: quoteid=10
quotenumber - Autotask external quote number to export
    Usage: quotenumber=1023
quotename - Autotask quote name to export
    Usage: quotename="Color Laser Printer"

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
