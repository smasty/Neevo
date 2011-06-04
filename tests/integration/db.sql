-- Table schema
CREATE TABLE author (
  id integer NOT NULL PRIMARY KEY,
  name text NOT NULL,
  url text NOT NULL
);

CREATE TABLE software (
  id integer NOT NULL PRIMARY KEY,
  author_id integer NOT NULL,
  title text NOT NULL,
  url text NOT NULL,
  FOREIGN KEY (author_id) REFERENCES author (id)
);


-- Table data
INSERT INTO author (id, name, url) VALUES (11, 'Linus Torvalds', 'http://en.wikipedia.org/wiki/Linus_Torvalds');
INSERT INTO author (id, name, url) VALUES (12, 'Dries Buytaert', 'http://buytaert.net');
INSERT INTO author (id, name, url) VALUES (13, 'David Grudl', 'http://davidgrudl.com');

INSERT INTO software (author_id, title, url) VALUES (11, 'Linux kernel', 'http://kernel.org');
INSERT INTO software (author_id, title, url) VALUES (11, 'Git', 'http://git-scm.com');
INSERT INTO software (author_id, title, url) VALUES (12, 'Drupal', 'http://drupal.org');
INSERT INTO software (author_id, title, url) VALUES (12, 'Acquia', 'http://acquia.com');
INSERT INTO software (author_id, title, url) VALUES (13, 'Nette Framework', 'http://nette.org');
INSERT INTO software (author_id, title, url) VALUES (13, 'Texy!', 'http://texy.info');
