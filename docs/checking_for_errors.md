# Checking for Errors

- `bin\cake check-locations` searches the database for a wide range of problems with school and school district records,
  including:
   - Schools/districts without IDOE codes, cities, counties, states, or statistics
   - Schools without school types (public, private, etc.), addresses, or grade levels
   - Public and charter schools without districts (though it's not necessarily an error for a charter school not to have
     a district)
   - Districts without schools
   - Schools/districts with multiple cities, counties, or states (though this is not necessarily indicative of an error)

- `bin\cake check-stats` searches the database for the following problems with statistical records:
   - Validation errors
   - Percentage values that aren't between 0% and 100%
   - Unselectable metrics with statistics 
   - Selectable metrics with no statistics
   - Metrics with no statistics for the most recent year found in the `statistics` table
