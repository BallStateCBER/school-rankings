# Updating Statistical Data

## Collecting Data
 - [This page](https://www.doe.in.gov/accountability/find-school-and-corporation-data-reports) lists links to 
   spreadsheets of statistical data.
 - Most of these spreadsheets have the same structure and should be downloaded and formatted. Others, like the "grade 
   summary" files, are outliers presenting data concerning the same metrics as other files, but with a different 
   structure. These outliers can be ignored. Similarly, the "school directory" spreadsheet has no statistical 
   information for any metrics tracked by this application and can also be ignored.
   
## Formatting Data
 - Each file must be split up into separate files for each year and placed into subdirectories named after the relevant 
   year.
 - Each file must _only_ contain date for that year and no others. Remove any worksheets, rows, or columns that are 
   about other years, and delete the "year(s)" column, if present.
 - If a school year is referred to as a span of two years, like "2015-2016", ignore the first year and consider that 
   just "2016".
 - Add the relevant year to each new spreadsheet's filename	(e.g. if `filename.xlsx` has data for years 2016 and 2017, 
   make copies called `filename (2016).xlsx` and `filename (2017).xlsx`).
 - Be aware that data for previous years may have already been separated into their own files, saved, and processed.
   Check to see which years of data are new and only save those.
 - Formatted spreadsheets may need to have extraneous rows removed from their tops in order to have the column headers
   or column grouping headers in the first row.
 - Formatted spreadsheets may need to have extraneous columns removed, such as those that don't contain statistical data
   being imported or the names or IDOE codes of schools or school corporations. The first column should contain IDOE 
   codes, and the second column should contain school/corporation names. Examples of columns to remove include those 
   that contain years or the public/nonpublic statuses of schools.
 - Some spreadsheets may have extraneous worksheets in various formats that describe state-level data. These can be 
   removed, as only data that pertains to specific schools or school corporations is to be imported.

## Saving Data
Finalized spreadsheets with statistical information about schools and corporations are to be saved in a Box directory 
(`CBER Box - Web > Projects > School Rankings > Statistics > {YEAR}`).

These files will then be copied into this codebase (`/data/statistics/{YEAR}`) and committed.

## Data Import Commands
 - `bin\cake import-stats-status` displays a list of all years, all statistics files for those years, and the date that 
   each file was imported (which will be blank if it has not yet been imported). Specifying a year, e.g. 
   `bin\cake import-stats-status 2019`, will show only the files for that year.
 - `bin\cake import-stats` will ask the user to select a year and a specific file and will import the data from that 
   file, creating new records for schools, school corporations, metrics, and statistics as necessary. This command is 
   safe to run repeatedly, as it will only add information that is not already in the database and update changed 
   information that was previously imported. To automate the processing of a large number of files, the year could be
   specified as "all" and the files selection can be "all" or "new", the latter of which will tell this script to 
   process any files that have not been processed yet.  
   - The `--auto-metrics` option will automatically create a new metric the first time it encounters any given spreadsheet
   column instead of asking for user input.
   - The `--overwrite` option will automatically update statistic records in the database if a spreadsheet has a different
   value.
 - `bin\cake populate-es` allows the user to delete and recreate the Elasticsearch index for statistical data. This is 
   the datasource that gets queried when schools or school corporations are ranked, so it is very important that updates 
   to the `statistics` MySQL table are followed by this command being run

## Metrics Housekeeping

The `import-stats` command tries its best to either find an existing metric that corresponds to a given spreadsheet 
column or to create a new metric with a reasonable name. To avoid creating unnecessary new metrics that would later need
to be merged with the correct existing metric, run `import-stats` _without_ the `--auto-metrics` option. You will be 
asked for either the ID of an existing metric or the name to give to a new metric for each spreadsheet column the first 
time it is encountered. The metric management pages at `/admin/metrics/school` and `/admin/metrics/district` can help
you find the IDs of existing metrics. 

Alternatively, the `--auto-metrics` option can be used in order to process one or more import files in an automated way, 
and the resulting new root-level metrics can be cleaned up with the following commands:

 - `bin\cake metric-merge` will take two metrics and merge one into the other, updating statistics and any other 
   associated records. 
 - `bin\cake metric-parent-merge` will take two parent metrics and merge their identically-named metrics.
 - `bin\cake metric-reparent` will take one or more metrics and reparent them so that they are children of another 
   specified metric.
 - `bin\cake metric-tree-clean` will find metrics that can be safely removed from the metric tree because they have no 
   associated statistics or children with associated statistics.  
