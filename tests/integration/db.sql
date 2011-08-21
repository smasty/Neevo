-- Table schema
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
INSERT INTO "author" ("id", "name", "url") VALUES (11, 'Linus Torvalds', 'http://en.wikipedia.org/wiki/Linus_Torvalds');
INSERT INTO "author" ("id", "name", "url") VALUES (12, 'Dries Buytaert', 'http://buytaert.net');
INSERT INTO "author" ("id", "name", "url") VALUES (13, 'David Grudl', 'http://davidgrudl.com');

INSERT INTO "software" ("id", "author_id", "title", "url") VALUES (1, 11, 'Linux kernel', 'http://kernel.org');
INSERT INTO "software" ("id", "author_id", "title", "url") VALUES (2, 11, 'Git', 'http://git-scm.com');
INSERT INTO "software" ("id", "author_id", "title", "url") VALUES (3, 12, 'Drupal', 'http://drupal.org');
INSERT INTO "software" ("id", "author_id", "title", "url") VALUES (4, 12, 'Acquia', 'http://acquia.com');
INSERT INTO "software" ("id", "author_id", "title", "url") VALUES (5, 13, 'Nette Framework', 'http://nette.org');
INSERT INTO "software" ("id", "author_id", "title", "url") VALUES (6, 13, 'Texy!', 'http://texy.info');
