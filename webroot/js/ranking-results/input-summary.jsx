import PropTypes from 'prop-types';
import React from 'react';

class InputSummary extends React.Component {
  constructor(props) {
    super(props);

    this.getSelectedGradesString = this.getSelectedGradesString.bind(this);
    this.getSchoolTypesString = this.getSchoolTypesString.bind(this);
  }

  /**
   * Returns a Map of all grade levels
   *
   * @return {Map<Number, Object>}
   */
  getAllGrades() {
    const gradeLevels = window.formulaForm.gradeLevels;
    return new Map(gradeLevels.map((grade) => [grade.id, grade]));
  }

  /**
   * Returns a string describing what grade levels have been selected
   *
   * @return {String}
   */
  getSelectedGradesString() {
    const allGrades = this.getAllGrades();
    const selectedGradeIds = this.props.submittedData.gradeIds.map(Number);
    if (selectedGradeIds.length === 0 || selectedGradeIds.length === allGrades.size) {
      return 'all grade levels';
    }

    selectedGradeIds.sort((a, b) => a - b);
    const namedGradeLevels = [];
    const numberedGradeLevels = [];
    for (let i = 0; i < selectedGradeIds.length; i++) {
      const gradeId = selectedGradeIds[i];
      const selectedGrade = allGrades.get(gradeId);
      const name = selectedGrade.name.toLowerCase();
      if (name.search('grade') === -1) {
        namedGradeLevels.push(name);
        continue;
      }
      const gradeNumber = Number(name.replace('grade ', ''));
      numberedGradeLevels.push(gradeNumber);
    }

    // Shorten contiguous groups of grades (9, 10, 11, 12) into ranges (9-12)
    const retval = namedGradeLevels;
    let rangeStart = null;
    let rangeEnd = null;
    for (let i = 0; i < numberedGradeLevels.length; i++) {
      rangeStart = numberedGradeLevels[i];
      rangeEnd = rangeStart;
      while (numberedGradeLevels[i + 1] - numberedGradeLevels[i] === 1) {
        rangeEnd = numberedGradeLevels[i + 1];
        i++;
      }
      const range = rangeStart === rangeEnd ? 'grade ' + rangeStart : 'grades ' + rangeStart + '-' + rangeEnd;
      retval.push(range);
    }

    if (retval.length > 1) {
      const lastIndex = retval.length - 1;
      retval[lastIndex] = 'and ' + retval[lastIndex];
    }
    const delimiter = retval.length > 2 ? ', ' : ' ';

    return retval.join(delimiter);
  }

  /**
   * Returns a single lowercase string describing all of the selected school types
   *
   * @return {string}
   */
  getSchoolTypesString() {
    if (this.props.submittedData.onlyPublic) {
      return 'public';
    }

    const selectedSchoolTypeIds = this.props.submittedData.schoolTypeIds.map(Number);
    if (selectedSchoolTypeIds.length === 0) {
      return '';
    }

    const allSchoolTypes = window.formulaForm.schoolTypes;
    const selectedSchoolTypeNames = [];
    for (let i = 0; i < allSchoolTypes.length; i++) {
      const schoolType = allSchoolTypes[i];
      if (selectedSchoolTypeIds.indexOf(schoolType.id) !== -1) {
        selectedSchoolTypeNames.push(schoolType.name);
      }
    }

    if (selectedSchoolTypeNames.length > 1) {
      const lastKey = selectedSchoolTypeNames.length - 1;
      selectedSchoolTypeNames[lastKey] = 'and ' + selectedSchoolTypeNames[lastKey];
    }

    const delimiter = selectedSchoolTypeNames.length > 2 ? ', ' : ' ';

    return selectedSchoolTypeNames.join(delimiter);
  }

  render() {
    let subjectString;
    if (this.props.submittedData.context === 'school') {
      const schoolTypes = this.getSchoolTypesString();
      subjectString = schoolTypes ? schoolTypes + ' schools' : 'schools';
      subjectString += ' teaching ' + this.getSelectedGradesString();
    } else {
      subjectString = 'school corporations';
    }

    const criteria = this.props.submittedData.criteria;
    let key = 0;
    return (
      <div>
        <p>
          Ranking {subjectString} according to:
        </p>
        <table className="table table-sm">
          <thead>
            <tr>
              <th>
                Metric
              </th>
              <th>
                Importance
              </th>
            </tr>
          </thead>
          <tbody>
            {Array.from(criteria.values()).map(function(criterion) {
              key++;
              return (
                <tr key={'input-summary-criterion-' + key}>
                  <td>
                    {criterion.metric.name}
                  </td>
                  <td>
                    {criterion.weight}%
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    );
  }
}

InputSummary.propTypes = {
  submittedData: PropTypes.object.isRequired,
};

export {InputSummary};
