# Miscellaneous Notes

## Statistics
 - Statistics that apply to a school year that spans two calendar years (e.g. 2005-2006) will be saved with only the 
   latter year in the `statistics.year` column.
 - If a statistic record's `contiguous` field is `true`, that means it can be compared to the previous year in a line 
   graph. Some metrics may use a different calculation method from one year to the next, resulting in a time series 
   where one year's value is not comparable with the previous. In this case, line graphs will need to either note the 
   change or only use the most recent contiguous series of values.

## Rankings
 - If a school ranking record has no associated school grade levels (i.e. a record in the `rankings` table with 
   `for_school_districts` == `false` has no associated records in the `rankings_grades` table), it is assumed that 
   schools which teach _any_ grade level are being ranked. 
