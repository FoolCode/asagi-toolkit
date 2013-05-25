ALTER TABLE "%%BOARD%%_threads"
ADD COLUMN "time_last_modified" INT(10) UNSIGNED NOT NULL AFTER "time_ghost_bump",
ADD COLUMN "sticky" TINYINT(1) DEFAULT 0 NOT NULL AFTER "nimages",
ADD COLUMN "locked" TINYINT(1) DEFAULT 0 NOT NULL AFTER "sticky",
ADD INDEX "time_last_modified_index" ("time_last_modified"),
ADD INDEX "sticky_index" ("sticky"),
ADD INDEX "locked_index" ("locked");
