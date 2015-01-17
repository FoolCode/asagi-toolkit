/**
 * Asagi Rebuild Tool
 */

var fs = require('fs-extra');
var cmd = require('commander');
var sql = require('knex')({
  client: 'mysql',
  connection: require('./config.json'),
  pool: {
    min: 0
  }
});

/**
 * Main Code
 */

cmd
  .version('0.0.2')
  .option('-b, --board <name>', 'board')
  .option('-d, --src-database <name>', 'database1')
  .option('-f, --dst-database <name>', 'database2')
  .option('-s, --src-path <path>', 'mediaSrc')
  .option('-p, --dst-path <path>', 'mediaDst')
  .option('-i, --process-images', 'images', true, false)
  .option('-t, --process-thumbs', 'thumbs', true, false)
  .option('-o, --offset <int>', 'offset')
  .parse(process.argv);

var board = cmd.board;
var src_database = cmd.database1 || 'asagi';
var dst_database = cmd.database2 || 'asagi';
var src_path = cmd.mediaSrc || '/home/archive/src/board/';
var dst_path = cmd.mediaDst || '/home/archive/dst/board/';
var process_images = cmd.images;
var process_thumbs = cmd.thumbs;
var offset = cmd.offset || 0;

var pad = function (s, w, z) {
  z = z || '0'; s = s + '';
  return s.length >= w ? s : new Array(w - s.length + 1).join(z) + s;
};

var restore = function (info, src, dst) {
  fs.exists(src.path + src.file, function (exists) {
    if (exists) {
      fs.exists(dst.path + dst.file, function (exists) {
        if (exists === false) {
          fs.mkdirs(dst.path, function (err) {
            if (err) return console.error(err);
            fs.copySync(src.path + src.file, dst.path + dst.file);
            console.log('+ [' + pad(info.num, info.len) + '] ' + dst.file);
          });
        } else {
          console.log('= [' + pad(info.num, info.len) + '] ' + dst.file);
        }
      });
    } else {
      console.log('- [' + pad(info.num, info.len) + '] ' + dst.file);
    }
  });
};

var rebuild = function (info, data, max_len, max_num) {
  if (data.src.media_id !== null && data.dst.banned === 0) {
    // process images
    if (info.images) {
      if (data.src.media !== null && data.dst.media !== null) {
        restore(
          { num: data.dst.media_id, len: max_len },
          { path: info.srcPath + info.board + '/image/' + data.src.media.substr(0, 4) + '/' + data.src.media.substr(4, 2) + '/', file: data.src.media },
          { path: info.dstPath + info.board + '/image/' + data.dst.media.substr(0, 4) + '/' + data.dst.media.substr(4, 2) + '/', file: data.dst.media }
        );
      }
    }

    // process thumbs
    if (info.thumbs === true) {
      if (data.src.preview_op !== null && data.dst.preview_op !== null) {
        restore(
          { num: data.dst.media_id, len: max_len },
          { path: info.srcPath + info.board + '/image/' + data.src.preview_op.substr(0, 4) + '/' + data.src.preview_op.substr(4, 2) + '/', file: data.src.preview_op },
          { path: info.dstPath + info.board + '/image/' + data.dst.preview_op.substr(0, 4) + '/' + data.dst.preview_op.substr(4, 2) + '/', file: data.dst.preview_op }
        );
      }

      if (data.src.preview_reply !== null && data.dst.preview_reply !== null) {
        restore(
          { num: data.dst.media_id, len: max_len },
          { path: info.srcPath + info.board + '/image/' + data.src.preview_reply.substr(0, 4) + '/' + data.src.preview_reply.substr(4, 2) + '/', file: data.src.preview_reply },
          { path: info.dstPath + info.board + '/image/' + data.dst.preview_reply.substr(0, 4) + '/' + data.dst.preview_reply.substr(4, 2) + '/', file: data.dst.preview_reply }
        );
      }
    }
  }
}

sql
  .max('media_id as total')
  .from(database2 + '.' + board + '_images').then(function (res) {
    var max_doc_id = res[0].total;
    var max_len_id = max_doc_id.toString().length;

    while (offset < max_doc_id) {
      knex.select(['src.*', 'dst.*'])
        .from(database2 + '.' + board + '_images as dst')
        .leftJoin(database1 + '.' + board + '_images as src', 'src.media_hash', '=', 'dst.media_hash')
        .whereBetween('dst.media_id', [parseInt(offset) - 5, parseInt(offset) + 1000])
        .options({ nestTables: true, rowMode: 'array' })
        .stream(function (stream) {
          stream.on('data', function (data) {
            rebuild({ board: board, srcPath: src_path, dstPath: dst_path, images: process_images; thumbs: process_thumbs }, data, max_len_id, max_doc_id);
          });
        });
      offset += 1000;
    }
  });
