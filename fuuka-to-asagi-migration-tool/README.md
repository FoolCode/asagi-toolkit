Fuuka to Asagi Migration Tool
=============================

### Syntax

`$ php migrate.php [options]`

Note: You will need to place a copy of your `asagi.json` file in this directory.

### Example

`$ php migrate.php --old-database fuuka --old-path /path/to/fuuka/boards/ --board a --process-thumbs --process-images`

### Description

This tool was written to help simplify the process of transition from Fuuka to Asagi. It should only be used on Fuuka dumps.

### Options

Options start with two dashes. Many of the options require an additional value next to them.


`--board <shortname>`

Processes only the board specified. By default, this tool will process all of the tables provided in the `asagi.json` file.

`--phase <stage>`

Processes only the stage specified. The input valude is an integer. By default, this tool will process a board through the following stages:

1. Rebuild Fuuka Tables

    This will create the Asagi tables for the board in the database provided in the `asagi.json` file. It will then begin to process the old Fuuka database provided with the `--old-database <name>` option and import them into the recently created Asagi tables. If the Asagi tables already exist, it will skip the table creation and proceed to the data processing.

2. Recreate Asagi Board Triggers and Procedures

    This would simply recreate the triggers and procedures for the board. It shouldn't be used with Asagi running in the background. This can be avoided by using the `--phase <stage>` option and specifying the other stages instead of this one.

3. Rebuild Media Directory (Thumbnails/Images)

    This would rebuild the old media directory to use the new folder structure and deduplication utilized in Asagi. You will need to pass the `--process-thumbs` and/or `--process-images` in order to rebuild them. Since this process only copies the files instead of moving them, you must make sure that enough space is available for this phase to complete. You are also required to pass the `--old-path <path>` option specifying the location of the old Fuuka media directory.

`--old-database <name>`

Specify the name of the database which contains the Fuuka tables.

`--old-path <path>`

Specify the path of the media directory containing the Fuuka media files.

`--process-thumbs`

Tells the tool to process thumbnails.

`--process-images`

Tells the tool to process the full images.
