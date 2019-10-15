class Analytics {
  constructor(state) {
    this.state = state;
  }

  /**
   * Returns a string representing all selected grade level ranges, combining adjacent numeric grade levels
   *
   * e.g. Grades 1-5 and Grades 6-8 get combined into Grades 1-8
   *
   * @param {Array} gradeLevelRanges
   * @return {string|null}
   */
  joinGradeLevelRanges(gradeLevelRanges) {
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
  getRangeForGradeLevel(gradeLevelName) {
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
      const gradeRange = this.getRangeForGradeLevel(gradeLevelName);
      if (gradeLevelRanges.indexOf(gradeRange) === -1) {
        gradeLevelRanges.push(gradeRange);
      }
    });

    return gradeLevelRanges;
  }

  /**
   * Sends an event to Google Analytics describing the selections made to determine the pool of subjects to be ranked
   */
  sendRankingPoolAnalyticsEvent() {
    const gradeLevels = this.state.analyticsPoolEventData.gradeLevels;
    const eventData = {
      hitType: 'event',
      eventCategory: 'Formula Form',
      eventAction: 'ranking pool',
      dimension1: this.state.analyticsPoolEventData.context,
      dimension2: this.state.analyticsPoolEventData.geographicArea,
      dimension3: this.state.analyticsPoolEventData.schoolTypes,
      dimension4: gradeLevels ? gradeLevels : 'Any grade level',
    };
    console.log(eventData);
  }

  /**
   * Returns an alphabetized string of all selected SchoolType names or NULL if none are selected
   *
   * @param {Array} schoolTypeIds
   * @return {null|String}
   */
  getSelectedSchoolTypesForAnalytics(schoolTypeIds) {
    if (schoolTypeIds.length === 0) {
      return null;
    }

    const schoolTypeNames = [];
    this.state.schoolTypes.forEach((schoolType) => {
      if (this.includes(schoolTypeIds, schoolType.name)) { // .name is actually the schoolType's ID
        schoolTypeNames.push(schoolType.label.toLowerCase());
      }
    });
    schoolTypeNames.sort();

    return schoolTypeNames.join(' / ');
  }

  getGradeLevelsForAnalytics() {
    const gradeLevelNames = this.getSelectedGradeNames();
    const gradeRanges = this.getRangesForGrades(gradeLevelNames);
    return this.joinGradeLevelRanges(gradeRanges);
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
