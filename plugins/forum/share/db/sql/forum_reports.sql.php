<?php
return '
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL DEFAULT \'0\',
  `user_id` int(11) NOT NULL DEFAULT \'0\',
  `time` int(11) NOT NULL DEFAULT \'0\',
  `text` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`id`)
';
