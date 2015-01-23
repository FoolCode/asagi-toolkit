INSERT IGNORE INTO "%%NEW_DATABASE%%"."%%BOARD%%"
(
	poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired,
	preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig,
	spoiler, deleted, capcode, email, name, trip, title, comment, delpass, sticky, locked, poster_hash, poster_country, exif
)
SELECT
	poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired,
	preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig,
	spoiler, deleted, capcode, email, name, trip, title, comment, delpass, sticky, locked, poster_hash, poster_country, exif
FROM "%%OLD_DATABASE%%"."%%BOARD%%"
