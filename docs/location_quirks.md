# School and School Corporation Quirks

The following are important facts to be aware of which might not be intuitive:
 - Schools and school corporations
   - The addresses listed in the "School Directory" spreadsheet released by the Indiana Department of Education (IDOE) 
     are in some cases the school/corporation's actual address and in other cases its mailing address, located elsewhere 
 - Schools
   - Occasionally, a school may teach past grade 12, such as schools that limit their students by age (e.g. 22) rather 
     than grade level
   - Schools may occasionally change their IDOE codes. When this happens, the `update-idoe-codes` command should be used
     to add the school's new code. All of the IDOE codes that a school has _ever_ used are stored in the database so 
     that statistics files can be processed correctly no matter how old the information (and the manner of identifying 
     each school) is. 
   - A school's associated corporation can also change, but this application doesn't care about a school's previous 
     corporations in the same way as it does with IDOE codes. Only the school's current corporation matters, as that is
     part of the information that is displayed to users.
 - Private schools
   - These are not required to submit their website URLs to the IDOE
   - These don't have corresponding school corporations
 - Charter schools
   - Each charter school may or may not have a corresponding school corporation 
 - School corporations
   - The nonstandard corporations called "Community-based Preschools" (code 8801) and "GQE Retest Site" (code 9700) are
     reported on by IDOE but are ignored by this application
