Asagi Rebuild Tool
==================

**Disclaimer: This tool have been heavily modified from the original code to allow for portability, but have not been tested to ensure that they work properly. Please notify us of any issues that you might encounter while using them.**

### Syntax

```
$ node asagi-rebuild.js [options]
```

### Install

```
$ npm install commander fs-extra knex mysql
$ cp lib/runner.js node_module/knex/lib/dialects/mysql/runner.js
$ cp config.json.example config.json
$ (nano|vi) config.json
```

### Example

```
$ node asagi-rebuild.js -b a -d asagi -f asagi_dump -s "/var/data/asagi-dump/" -p "/var/data/boards/" -i -t
```
Note: This will tell the tool to use the data from `asagi_dump` to import the media files located at `/var/data/asagi-dump/` into `/var/data/boards/`.

### Description

This tools was written to help simplify the process of rebuilding/importing Asagi data dumps. It is designed to only work with Asagi data dumps.

**Note: Both the board source and destination `images` table are required to rebuild or import data.**

### Options

Options start with two dashes. Many of the options require an additional value next to them.

`-b <name>`, `--board <name>`

Processes the board specified.

`-d <name>`, `--src-database <name>`

The database source you wish to rebuild or import the data with.

`-f <name>`, `--dst-database <name>`

The database destination you wish to rebuild or import the data into.

`-s <path>`, `--src-path <path>`

The media directory source you wish to rebuild or import the data from.

`-p <path>`, `--dst-path <path>`

The media directory destination you wish to rebuild or import the data into.

`-i`, `--process-images`

Tells the tool to process the full images.

`-t`, `--process-thumbs`

Tells the tool to process thumbnails.

`-o <offset>`, `--offset <int>`

Specifies where the tool should start processing the dump at.
