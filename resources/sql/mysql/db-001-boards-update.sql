UPDATE "%%BOARD%%_threads"
SET time_last_modified = COALESCE(GREATEST(time_last, IFNULL(time_ghost, 0)));
