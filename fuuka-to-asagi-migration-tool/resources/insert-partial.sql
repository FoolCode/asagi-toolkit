INSERT IGNORE INTO "%%NEW_DATABASE%%"."%%BOARD%%"
(
	poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired,
	preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig,
	spoiler, deleted, capcode, email, name, trip, title, comment, delpass, sticky, locked, poster_hash, poster_country, exif
)
SELECT 
	id, num, subnum, IF(parent=0,num,parent), IF(parent=0,1,0), timestamp, NULL,
	preview, preview_w, preview_h, media, media_w, media_h, media_size, media_hash, media_filename,
	spoiler, deleted, capcode, email, name, trip, title, comment, delpass, sticky, 0, NULL, NULL, NULL
FROM "%%OLD_DATABASE%%"."%%BOARD%%"
WHERE doc_id >= %%START_DOC_ID%% AND doc_id <= %%END_DOC_ID%%