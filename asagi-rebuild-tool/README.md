Asagi Rebuild Tool
==================

**Disclaimer: This tool have been heavily modified from the original code to allow for portability, but have not been tested to ensure that they work properly. Please notify us of any issues that you might encounter while using them.**

### Syntax

`$ node asagi-rebuild.js [options]`

### Install

```
$ npm install commander fs-extra knex mysql
$ cp lib/runner.js node_module/knex/lib/dialects/mysql/runner.js
$ cp config.json.example config.json
$ (nano|vi) config.json
```

### Usage

Refer to the source code or ask.

### Description

This tools was written to help simplify the process of rebuilding/importing Asagi data dumps. It is designed to only work with Asagi data dumps.
