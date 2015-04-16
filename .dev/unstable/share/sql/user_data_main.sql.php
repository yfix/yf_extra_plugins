<?php
$data = '
	`id` int(10) unsigned NOT NULL auto_increment,
	`group` tinyint(3) unsigned NOT NULL default \'0\',
	`name` varchar(255) NOT NULL default \'\',
	`nick` varchar(64) NOT NULL default \'\',
	`login` varchar(64) NOT NULL default \'\',
	`email` varchar(128) NOT NULL,
	`password` varchar(12) NOT NULL default \'\',
	`phone` varchar(40) NOT NULL default \'\',
	`fax` varchar(40) NOT NULL default \'\',
	`address` varchar(255) NOT NULL default \'\',
	`city` varchar(40) NOT NULL default \'\',
	`zip_code` varchar(16) NOT NULL default \'\',
	`state` varchar(20) NOT NULL default \'\',
	`country` varchar(30) NOT NULL default \'USA\',
	`sex` enum(\'Female\',\'Male\',\'Transsexual\') NOT NULL,
	`birth_date` date NOT NULL,
	`profile_url` varchar(64) NOT NULL,
	`url` varchar(100) NOT NULL default \'\',
	`show_mail` enum(\'0\',\'1\') NOT NULL,
	`verify_code` varchar(32) NOT NULL default \'\',
	`admin_comments` text NOT NULL,
	`avatar` varchar(255) NOT NULL,
	`lon` decimal(8,4) NOT NULL default \'0.0000\',
	`lat` decimal(8,4) NOT NULL default \'0.0000\',
	`is_deleted` enum(\'0\',\'1\') NOT NULL,
	`active` tinyint(1) unsigned NOT NULL default \'1\',
	`level` tinyint(1) unsigned NOT NULL default \'0\',
	PRIMARY KEY  (`id`),
	KEY `login` (`login`),
	KEY `nick` (`nick`),
	KEY `active` (`active`),
	KEY `group` (`group`),
	KEY `email` (`email`)
';