<?php
/***************************************************************
 * Extension Manager/Repository config file for ext "linkvalidator".
 *
 * Auto generated 23-10-2011 17:09
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/
$EM_CONF[$_EXTKEY] = array(
	'title' => 'Link Validator',
	'description' => 'Link Validator checks the links in your website for validity. It can validate all kinds of links: internal, external and file links. Scheduler is supported to run Link Validator via Cron including the option to send status mails, if broken links were detected.',
	'category' => 'module',
	'author' => 'Jochen Rieger / Dimitri König / Michael Miousse',
	'author_email' => 'j.rieger@connecta.ag, mmiousse@infoglobe.ca',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'author_company' => 'Connecta AG / cab services ag / Infoglobe',
	'version' => '6.3.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.3.0-6.3.99',
			'info' => '6.3.0-6.3.99',
		),
		'conflicts' => array(),
		'suggests' => array(),
	),
	'_md5_values_when_last_written' => '',
);
