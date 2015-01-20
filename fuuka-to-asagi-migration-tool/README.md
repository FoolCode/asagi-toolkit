Fuuka to Asagi Migration Tool
=============================

### Syntax

```
$ php fuuka migrate [options]
```

### Install

```
$ composer install
$ cp config.json.example config.json
$ (nano|vi) config.json
```

### Example

```
$ php fuuka migrate -b a -d fuuka -p /path/to/fuuka/boards/ -i -t
```

### Description

This tool was written to help simplify the process of transition from Fuuka to Asagi. It should only be used on Fuuka dumps.

### Options

Options start with two dashes. Many of the options require an additional value next to them.


`-b <board>`, `--board <board>`

Processes only the board specified. By default, this tool will process all of the tables provided in the `asagi.json` file.

`-s <stage>`, `--phase <stage>`

Processes only the stage specified. The input value is an integer. By default, this tool will process a board through the following stages:

1. Rebuild Fuuka Tables

    This will create the Asagi tables for the board in the database provided in the `asagi.json` file. It will then begin to process the old Fuuka database provided with the `--import-database <name>` option and import them into the recently created Asagi tables. If the Asagi tables already exist, it will skip the table creation and proceed to the data processing.

2. Rebuild Media Directory (Thumbnails/Images)

    This would rebuild the old media directory to use the new folder structure and deduplication utilized in Asagi. You will need to pass the `--process-thumbs` and/or `--process-images` in order to rebuild them. Since this process only copies the files instead of moving them, you must make sure that enough space is available for this phase to complete. You are also required to pass the `--import-path <path>` option specifying the location of the old Fuuka media directory.

`-c <size>`, `--chunk <size>`

Controls the size of each data chunk processed. If the database is fairly active, this option can be used to minimize the amount of deadlocks encountered. (Default: 1000)

`-d <database>`, `--import-database <database>`

Specify the name of the database which contains the Fuuka tables.

`-p <path>`, `--import-path <path>`

Specify the path of the media directory containing the Fuuka media files.

`-i`, `--process-images`

Tells the tool to process the full images.

`-t`, `--process-thumbs`

Tells the tool to process thumbnails.
