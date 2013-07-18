CFS_Autotask_Southware
======================

Script + configuration to support integration of Autotask and Southware developed for CFS

Usage
-----

Usage Example:

    PROMPT>php cfs_at_sw.php

OPTIONAL COMMAND LINE PARAMETERS

**ini** - Path to an ini file*  
    Usage: ini=foo.ini  
    Usage: ini=c:/temp/foo.ini  
    Usage: ini="c:/dir name with spaces/file name with spaces.ini"

**invoice_skip_columns** - invoice column names, separated by commas,    
    which will not be output to Southware  
    Usage: invoice_skip_columns="TASK OR TICKET TITLE,TASK OR TICKET SUMMARY"
    
**test_mail** - sends test message to the notify_results email list  
    Usage: test_mail

**test_error_mail** - sends test message to the notify_errors email list  
    Usage: test_error_mail

**version** - returns current program version  
    Usage: version

**help** - returns this information

*Note: if no ini command line parameter supplied, any *.ini file in the same directory and having the same base name as this script will be used!*

Configuration (ini directives)
------------------------------

Documented in sample ini file.

Script Workflow
---------------

### Step 1: Update Autotask accounts with Southware account numbers ###

The script looks for files in the directory defined by the **accounts_southware_dir** ini directive. These files have been created externally and include two columns in a tab-delimited format: Southware Account Number & Autotask Account ID. Because the script polls a directory as opposed to a single file, unique file names (e.g., using a datetime stamp) are allowed and encouraged. If no files are found, this step is skipped (go to Step 2).

For each file, and every row in the files, a matching Autotask Account record is located based on the Autotask Account ID and the Autotask Account Number field is updated with the Southware Account Number.

If all records from a tab-delimited file found in the directory defined by the **accounts_southware_dir** ini directive are successfully updated in Autotask, the file will be archived in the directory defined by the **accounts_southware_archive_dir** ini directive.

### Step 2: Update Autotask contracts with Southware contract numbers ###

The script looks for files in the directory defined by the **contracts_southware_dir** ini directive. These files have been created externally and include two columns in a tab-delimited format: Southware Contract Number & Autotask Contract ID. Because the script polls a directory as opposed to a single file, unique file names (e.g., using a datetime stamp) are allowed and encouraged. If no files are found, this step is skipped (go to Step 3).

For each file, and every row in the files, a matching Autotask Contract record is located based on the Autotask Contract ID and the Autotask Contract Number field is updated with the Southware Contract Number.

If all records from a tab-delimited file found in the directory defined by the **contracts_southware_dir** ini directive are successfully updated in Autotask, the file will be archived in the directory defined by the **contracts_southware_archive_dir** ini directive.

### Step 3: Prepare Autotask invoice batch export(s) for import into Southware as invoices ###

The script looks for files in the directory defined by the **invoices_autotask_dir** ini directive. These files are generated by running the invoicing process in Autotask and exporting the invoices as a CSV file. [Autotask configuration options discussed here?] Because the script polls a directory as opposed to a single file, unique file names (e.g., using a datetime stamp) are allowed and encouraged.  If no files are found, this step is skipped and script execution ends.

A number of script ini directives are used while processing Autotask invoice export files. The script uses the first row ("header record") from the Autotask export file to map the columns specified in the ini directives.

1. The **invoice_skip_columns** ini directive, if defined, contains one or more column names, separated by commas, which will not be output to the Southware invoice import file. Implied here: All columns *not* specified in the **invoice_skip_columns** ini directive *are* output to the Southware invoice import file!
2. The **invoice_truncate_column** ini directive, if defined, contains a column name which may commonly contain data that is not CSV-compatible due to a bug in Autotask. (Double-quotes are not escaped correctly in the Autotask CSV data!) If Autotask's CSV does not parse correctly (too many columns found), then the column specified by the **invoice_truncate_column** ini directive is "truncated" in order to correctly align the remaining columns. If the **invoice_truncate_column** ini directive is not specified and too many columns are found for a row, then an error will be generated and the entire file will be skipped.
3. The **invoices_account_id_field** ini directive is required and is used to indicate which column in the Autotask invoice export file contains the Autotask account id field.
4. The **invoices_account_number_field** ini directive is required and is used to indicate which column in the Autotask invoice export file contains the Autotask account number field.
5. The **contracts_allocation_code** ini directive, if defined, contains one or more contract codes, separated by commas, which will be checked for in Autotask's "ALLOCATION CODE EXTERNAL NUMBER" field.

Using the directives indicated above, each row of each file is processed:

1. If no Autotask account number is defined in a row of the Autotask invoice export file, Autotask is queried directly to return the account number (which may have been updated in the meantime either manually or during Step 1 above).
2. If any row is missing an Autotask account number and the account number cannot be found in Autotask directly, then the entire Autotask invoice export file is skipped and left in the directory defined by the **invoices_autotask_dir** ini directive, where future attempts will be made to process it again.
2. If (a) Autotask account number field is defined, (b) Autotask's "ALLOCATION CODE EXTERNAL NUMBER" field matches one of the values specified in the **contracts_allocation_code** ini directive, AND (c) no EXTERNAL CONTRACT NUMBER is defined in a row of the Autotask invoice export file, Autotask is queried directly to return the contract number (which may have been updated in the meantime either manually or during Step 2 above).
2. If any row is missing an Autotask contract number and the contract number cannot be found in Autotask directly, then the entire Autotask invoice export file is skipped and left in the directory defined by the **invoices_autotask_dir** ini directive, where future attempts will be made to process it again.
3. If all Autotask account numbers are present AND contract numbers are present (for those rows with matching allocation codes), then the file is formatted for Southware's CSV & date standards and copied to the directory specified by the **invoices_southware_dir** ini directive. The original Autotask invoice export file is then moved to the directory specified by the **invoices_autotask_archive_dir** ini directive.

### Step 4: Create Southware account import file ###

If during Step 3 above an Autotask invoice export file cannot be successfully processed because Autotask account numbers are missing, then the corresponding account data is exported to a tab-delimited file and placed in the directory specified by the **accounts_autotask_dir** ini directive.

Exported fields include:

- Autotask Account ID
- Customer Number
- Customer Name
- Address Line 1
- Address Line 2
- City
- State
- Zip Code
- Contact
- EMail Address
- Phone Number
- Fax No
- Salesperson Number
- Salesperson Name
- Salesperson Initials
- Customer Type
- Apply Finance Chrgs?
- Ship Via Code
- Group Number
- Tax Code
- Date Customer Added
- Web Address

### Step 5: Create Soutware contract import file ###

If during Step 3 above an Autotask invoice export file cannot be successfully processed because Autotask contract numbers are missing, then the corresponding contract data is exported to a CSV file and placed in the directory specified by the **contracts_autotask_dir** ini directive.

Exported fields include (all-caps fields refer to fields from the Autotask invoice export file!):

- Autotask Contract ID
- CLIENT NUMBER
- EXTERNAL INVOICE NUMBER
- ALLOCATION CODE EXTERNAL NUMBER
- EXTENDED PRICE
- QUANTITY
- EXTERNAL CONTRACT NUMBER
- ITEM NUMBER
- SERVICE PERIOD START DATE
- SERVICE PERIOD END DATE
- BILL TO CLIENT
- BILL TO CLIENT ADDRESS 1
- BILL TO CLIENT ADDRESS 2
- BILL TO CLIENT CITY
- BILL TO CLIENT STATE
- BILL TO CLIENT ZIP CODE
