CREATE TABLE `author` (
  `id` tinyint(4) NOT NULL,
  `name` varchar(256) COLLATE utf8_bin NOT NULL,
  `web` varchar(256) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `author` (`id`, `name`, `web`) VALUES
(11,	'Martin Srank',	'http://smasty.net'),
(12,	'Linus Torvalds',	'http://torvalds-family.blogspot.com');

CREATE TABLE `software` (
  `id` tinyint(4) NOT NULL,
  `aid` tinyint(4) NOT NULL,
  `title` varchar(256) COLLATE utf8_bin NOT NULL,
  `web` varchar(256) COLLATE utf8_bin NOT NULL,
  `slogan` varchar(256) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `software` (`id`, `aid`, `title`, `web`, `slogan`) VALUES
(1,	11,	'Neevo',	'http://neevo.smasty.net',	''),
(2,	12,	'Linux kernel',	'http://linux.org',	''),
(3,	11,	'Blabshare',	'http://labs.smasty.net',	''),
(4,	12,	'Git',	'http://git-scm.com',	'');