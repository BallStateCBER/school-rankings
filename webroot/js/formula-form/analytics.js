import ReactGA from 'react-ga';

class Analytics {
  constructor(FormulaForm) {
    this.getSelectedSchoolTypes = FormulaForm.getSelectedSchoolTypes;
    this.state = FormulaForm.state;
    this.submittedData = FormulaForm.submittedData;
    const trackingId = 'UA-32998887-12';
    ReactGA.initialize(trackingId, {
      debug: this.state.debug,
    });
  }

  /**
   * Returns a string representing all selected grade level ranges, combining adjacent numeric grade levels
   *
   * e.g. Grades 1-5 and Grades 6-8 get combined into Grades 1-8
   *
   * @param {Array} gradeLevelRanges
   * @return {string|null}
   */
  joinGradeRanges(gradeLevelRanges) {
    if (!gradeLevelRanges) {
      return null;
    }

    const formattedRanges = [];
    const preschoolName = this.getGradePreschool();
    const preKName = this.getGradePreK();
    const kName = this.getGradeK();
    [preschoolName, preKName, kName].forEach((gradeLevelName) => {
      if (this.includes(gradeLevelRanges, gradeLevelName)) {
        formattedRanges.push(gradeLevelName.split(' ')[0]);
      }
    });

    // Combine adjacent grade ranges
    if (this.includes(gradeLevelRanges, 'Grades 1-5')) {
      if (this.includes(gradeLevelRanges, 'Grades 6-8')) {
        if (this.includes(gradeLevelRanges, 'Grades 9-12')) {
          formattedRanges.push('Grades 1-12');
        } else {
          formattedRanges.push('Grades 1-8');
        }
      } else {
        formattedRanges.push('Grades 1-5');
      }
    } else {
      if (this.includes(gradeLevelRanges, 'Grades 6-8')) {
        if (this.includes(gradeLevelRanges, 'Grades 9-12')) {
          formattedRanges.push('Grades 6-12');
        } else {
          formattedRanges.push('Grades 6-8');
        }
      }
    }
    if (this.includes(gradeLevelRanges, 'Grades 9-12') && !this.includes(gradeLevelRanges, 'Grades 6-8')) {
      formattedRanges.push('Grades 9-12');
    }

    return formattedRanges.join(', ');
  }

  includes(haystack, needle) {
    return haystack.indexOf(needle) !== -1;
  }

  /**
   * Returns the name for the grade level "Pre-school (ages 0-2)"
   *
   * @return {string}
   */
  getGradePreschool() {
    return this.getGradeLevelName(1);
  }

  /**
   * Returns the name for the grade level "Pre-kindergarten (ages 3-5)"
   *
   * @return {string}
   */
  getGradePreK() {
    return this.getGradeLevelName(2);
  }

  /**
   * Returns the name for the grade level "Kindergarten"
   *
   * @return {string}
   */
  getGradeK() {
    return this.getGradeLevelName(3);
  }

  /**
   * Returns the name of the grade level corresponding to the provided ID
   *
   * @param {number} gradeLevelId
   * @return {string}
   */
  getGradeLevelName(gradeLevelId) {
    let retval = null;
    this.state.gradeLevels.forEach((gradeLevel) => {
      if (parseInt(gradeLevel.id) === gradeLevelId) {
        retval = gradeLevel.label;
      }
    });

    return retval;
  }

  /**
   * Returns a string describing the grade level range the provided grade level is in
   *
   * @param {string} gradeLevelName
   * @return {string|null}
   */
  getRangeForGrade(gradeLevelName) {
    switch (gradeLevelName) {
      case this.getGradePreschool():
      case this.getGradePreK():
      case this.getGradeK():
        return gradeLevelName;
      case 'Grade 1':
      case 'Grade 2':
      case 'Grade 3':
      case 'Grade 4':
      case 'Grade 5':
        return 'Grades 1-5';
      case 'Grade 6':
      case 'Grade 7':
      case 'Grade 8':
        return 'Grades 6-8';
      case 'Grade 9':
      case 'Grade 10':
      case 'Grade 11':
      case 'Grade 12':
        return 'Grades 9-12';
    }

    console.error('Grade level ' + gradeLevelName + ' not recognized');

    return null;
  }

  /**
   * Returns an array of unique grade ranges corresponding to the provided grade level names
   *
   * @param {Array} gradeLevelNames
   * @return {Array|null}
   */
  getRangesForGrades(gradeLevelNames) {
    if (!gradeLevelNames) {
      return null;
    }
    const gradeLevelRanges = [];
    gradeLevelNames.forEach((gradeLevelName) => {
      const gradeRange = this.getRangeForGrade(gradeLevelName);
      if (gradeLevelRanges.indexOf(gradeRange) === -1) {
        gradeLevelRanges.push(gradeRange);
      }
    });

    return gradeLevelRanges;
  }

  getContext() {
    const context = this.submittedData.context;
    switch (context) {
      case 'school':
        return context;
      case 'district':
        return 'school corporations';
      default:
        console.error('Unrecognized context: ' + context);
        return null;
    }
  }

  getGeographicArea() {
    const county = this.state.county;
    return county ? county.label + ' County, IN' : null;
  }

  getSchoolTypes() {
    if (this.state.context !== 'school') {
      return null;
    }

    if (this.state.onlyPublic) {
      return 'public';
    }

    const selectedSchoolTypeIds = this.getSelectedSchoolTypes();
    if (selectedSchoolTypeIds.length === 0) {
      return null;
    }

    const schoolTypeNames = [];
    this.state.schoolTypes.forEach((schoolType) => {
      if (this.includes(selectedSchoolTypeIds, schoolType.name)) { // .name is actually the schoolType's ID
        schoolTypeNames.push(schoolType.label.toLowerCase());
      }
    });
    schoolTypeNames.sort();

    return schoolTypeNames.join(' / ');
  }

  getGradeLevels() {
    if (this.state.context !== 'school') {
      return null;
    }

    const allGradesLabel = 'Any grade level';
    if (this.state.allGradeLevels) {
      return allGradesLabel;
    }

    const gradeNames = this.getSelectedGradeNames();
    if (gradeNames && gradeNames.length === this.state.gradeLevels.size) {
      return allGradesLabel;
    }

    const gradeRanges = this.getRangesForGrades(gradeNames);
    return this.joinGradeRanges(gradeRanges);
  }

  /**
   * Sends an event to Google Analytics describing the selections made to determine the pool of subjects to be ranked
   */
  sendRankingPoolAnalyticsEvent() {
    const eventData = {
      category: 'Formula Form',
      action: 'Set ranking pool',
    };
    const dimensions = {
      dimension1: this.getContext(),
      dimension2: this.getGeographicArea(),
      dimension3: this.getSchoolTypes(),
      dimension4: this.getGradeLevels(),
    };

    if (this.state.debug) {
      console.log(eventData);
      console.log(dimensions);
      return;
    }

    ReactGA.set(dimensions);
    ReactGA.event(eventData);
  }

  /**
   * Returns an array of selected grade level names
   *
   * @return {Array|null}
   */
  getSelectedGradeNames() {
    const selectedGradeRanges = [];
    this.state.gradeLevels.forEach(function(gradeLevel) {
      if (gradeLevel.checked) {
        selectedGradeRanges.push(gradeLevel.label);
      }
    });

    return selectedGradeRanges.length > 0 ? selectedGradeRanges : null;
  }
}

export {Analytics};
