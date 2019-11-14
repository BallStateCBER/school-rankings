# Fixing Errors

Several command-line scripts have been developed to help automate issues in the database.

- `bin\cake fix-district-associations` adds missing associations between school districts and their respective cities, 
  counties, and states, based on the location of their schools.

- `bin\cake fix-metric-tree` fixes problems with the metric tree structure by rewriting the `lft` and `rght` fields for 
  every metric. If the metric tree is displaying parent/child metric relationships that don't match what they should be,
  this command can  likely correct that.

- `bin\cake fix-percent-values` sets the `is_percent` field for each metric based on its name and either applies or
  removes percent formatting for each statistic based on what metric with which it's associated, e.g. transforming
  values like "0.025" into "2.5%".  

- `bin\cake fix-selectable` looks for unselectable metrics with associated statistics or selectable metrics with no 
  statistics and toggles their `selectable` flag. You can use this command to review a preview of the changes that it 
  will make before you precede with updates.
