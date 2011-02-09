
-- MySQL table schema
CREATE TABLE `author` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_bin NOT NULL,
  `url` varchar(255) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


CREATE TABLE `software` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8_bin NOT NULL,
  `url` varchar(255) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `software_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `author` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



-- SQLite table schema
CREATE TABLE "author" (
  "id" integer NOT NULL PRIMARY KEY,
  "name" text NOT NULL,
  "url" text NOT NULL
);

CREATE TABLE "software" (
  "id" integer NOT NULL PRIMARY KEY,
  "author_id" integer NOT NULL,
  "title" text NOT NULL,
  "url" text NOT NULL,
  FOREIGN KEY ("author_id") REFERENCES "author" ("id")
);




-- Table data
INSERT INTO author (id, name, url) VALUES (11, 'Linus Torvalds', 'http://en.wikipedia.org/wiki/Linus_Torvalds');
INSERT INTO author (id, name, url) VALUES (12, 'Dries Buytaert', 'http://buytaert.net');
INSERT INTO author (id, name, url) VALUES (13, 'David Grudl', 'http://davidgrudl.com');

INSERT INTO software (id, author_id, title, url) VALUES (1, 11, 'Linux kernel', 'http://kernel.org');
INSERT INTO software (id, author_id, title, url) VALUES (2, 11, 'Git', 'http://git-scm.com');
INSERT INTO software (id, author_id, title, url) VALUES (3, 12, 'Drupal', 'http://drupal.org');
INSERT INTO software (id, author_id, title, url) VALUES (4, 12, 'Acquia', 'http://acquia.com');
INSERT INTO software (id, author_id, title, url) VALUES (5, 13, 'Nette Framework', 'http://nette.org');
INSERT INTO software (id, author_id, title, url) VALUES (6, 13, 'Texy!', 'http://texy.info');
