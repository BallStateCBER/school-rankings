# Instructions for data collection for schools and school corporations

## School/Corporation Code Changes
Sometimes the Indiana Department of Education codes for a school may change, such as Oakland Elementary School, whose
code was changed from 8109 to 8108. Compiling this information allows the School Rankings website to recognize different
codes as referring to the same school.

1. Create a spreadsheet named `changed school and corporation doe codes (YEAR).xlsx` with YEAR being the current year.
2. Add sheets named "Schools" and "Corporations" and the following headers in the first row of each sheet
   - "Old IDOE Code"
   - "New IDOE Code"
   - either "School Name" or "Corporation Name"
   - "Notes"
3. Add information about code changes to this file.
4. Once finished adding data to this file, put it (or a copy of it) in the `data/locations/changed codes` directory of
   the School Rankings code base.
5. Run the `bin\cake update-idoe-codes` command and use this file as a reference when manually entering information.

Any schools/corporations whose Indiana Department of Education codes have changed should be added to this spreadsheet.
Information about how we know that the code had changed should be added to the "Notes" column, which can be the URL of a web page with more information.

Information about changed codes does not need to be limited to only changes that took place in the current year. Changes
can be noted in this spreadsheet no matter when they took place. It is only necessary to add any code changes that were
not reported in previous "changed school and corporation doe codes" files.

Suspected code changes can be proactively determined by looking for schools/corporations with no statistical data for
the current or a recent year. These results may either have a new code or may be closed.

## School/Corporation Info (location, contact info, etc.)
Note: It is recommended that code changes be processed before this step.

1. Download the current "School Directory" spreadsheet on [https://www.doe.in.gov/](https://www.doe.in.gov/) to collect
   information about public and non-public schools and rename it to `Indiana schools and corporations (YEAR).xlsx` with
   YEAR being the current year.
2. Rename the worksheets (if necessary) to CORP, PUBLIC, and PRIVATE, and add a sheet named CHARTER.
3. Use the list of current charter schools [found here](https://www.doe.in.gov/grants/resources-parents-and-families)
   to fill in the CHARTER sheet.
   1. Note that some schools are marked as being closed (ACTIVE value is "Inactive" and CLOSE_DATE value is non-null)
   and should be removed and added to the school/corporation closings spreadsheet described below.
   2. Then, the "ACTIVE" column should be deleted.
4. Once finished adding data to this file, put it (or a copy of it) in the `data/locations/open` directory of the School
Rankings code base.
5. Run the `bin\cake import-locations` command and select this file.

## School/Corporation Closings
It is important for the School Rankings website to know when schools are closed so that they are not displayed in
ranking results.

1. Create a spreadsheet named `closed schools and corporations (YEAR).xlsx` with YEAR being the current year.
2. Add sheets named "Schools" and "Corporations" and headers that read "IDOE School Code" or "IDOE Corporation Code",
   "Name", and "Notes" in the first row of each.
3. Add closed schools and corporations to this file.
4. Once finished adding data to this file, put it (or a copy of it) in the `data/locations/closed` directory of the
   School Rankings code base.
5. Run the `bin\cake import-closures` command and select this file.

Any schools/corporations that are determined to be closed should be added to this spreadsheet. The reason why it was
determined to be closed should also be added to the "Notes" column, which could either be a short description or the URL
of a web page with more information.

As long as each school/corporation is currently closed, it can be added no matter when the closure took place, but it is
only necessary to add the schools that closed since the last "closed schools and corporations" spreadsheet was compiled
and processed.

If a school/corporation has reopened, its status will be updated in the School Rankings database when an "Indiana
schools and corporations" spreadsheet is processed by the "bin\cake import-locations" command is run, provided that it
is included in that file.

To search for schools/corporations that may have been closed, check the database for any that have no associated
statistical data in the most recent year that data was reported for what is presumably all open schools and corporations
in the state. Any such results may not actually be closed, and may merely have changed their IDoE codes.

## Sources
In addition to the Indiana Department of Education website, the National Center for Education Statistics (NCES) is also
available for finding and confirming data about schools and districts.
 - [Public school search engine](https://nces.ed.gov/ccd/schoolsearch/)
 - [Private school search engine](https://nces.ed.gov/surveys/pss/privateschoolsearch/)
 - [Public school district search engine](https://nces.ed.gov/ccd/districtsearch/)
