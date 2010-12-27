CREATE TABLE `author` (
  `id` int(11) NOT NULL,
  `name` varchar(256) COLLATE utf8_bin NOT NULL,
  `web` varchar(256) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `author` (`id`, `name`, `web`) VALUES
(11,	'Martin Srank',	'http://smasty.net'),
(12,	'Linus Torvalds',	'http://torvalds-family.blogspot.com');

CREATE TABLE `software` (
  `id` int(11) NOT NULL,
  `aid` int(11) NOT NULL,
  `title` varchar(256) COLLATE utf8_bin NOT NULL,
  `web` varchar(256) COLLATE utf8_bin NOT NULL,
  `slogan` varchar(256) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `software` (`id`, `aid`, `title`, `web`, `slogan`) VALUES
(1,	11,	'Neevo',	'http://neevo.smasty.net',	'Lorem Ipsum-686'),
(2,	12,	'Linux kernel',	'http://linux.org',	'Lorem Ipsum-686'),
(3,	11,	'Blabshare',	'http://labs.smasty.net',	'Lorem Ipsum-686'),
(4,	12,	'Git',	'http://git-scm.com',	'Lorem Ipsum-686');

CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail` varchar(128) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;