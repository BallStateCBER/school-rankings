import PropTypes from 'prop-types';
import React from 'react';
import {Button} from 'reactstrap';
import {ResultSubject} from './result-subject.jsx';

class RankingResults extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      showAllStatistics: false,
    };
    this.contextIsSchool = this.contextIsSchool.bind(this);
    this.getInputSummary = this.getInputSummary.bind(this);
    this.getSchoolTypesString = this.getSchoolTypesString.bind(this);
    this.getSelectedGradesString = this.getSelectedGradesString.bind(this);
    this.toggleShowAllStatistics = this.toggleShowAllStatistics.bind(this);
  }

  getResultCell(subject) {
    let context = null;
    let subjectData = null;
    if (subject.hasOwnProperty('school')) {
      context = 'school';
      subjectData = subject.school;
    } else if (subject.hasOwnProperty('school_district')) {
      context = 'district';
      subjectData = subject.school_district;
    } else {
      console.log(
          'Error: Neither school nor school district found in result'
      );
      return;
    }
    return <ResultSubject subjectData={subjectData}
                          dataCompleteness={subject.data_completeness}
                          statistics={subject.statistics}
                          criteria={this.props.submittedData.criteria}
                          context={context}
                          showStatistics={this.state.showAllStatistics} />;
  }

  toggleShowAllStatistics() {
    this.setState({showAllStatistics: !this.state.showAllStatistics});
  }

  /**
   * Returns TRUE if the currently selected context is 'school' instead of 'school corporations'
   *
   * @return {boolean}
   */
  contextIsSchool() {
    return this.props.submittedData.context === 'school';
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
   * Returns an element with content that describes the selections that the user has made
   *
   * @return {*}
   */
  getInputSummary() {
    let subjectString;
    if (this.contextIsSchool()) {
      const schoolTypes = this.getSchoolTypesString();
      subjectString = schoolTypes ? schoolTypes + ' schools' : 'schools';
      subjectString += ' teaching ' + this.getSelectedGradesString();
    } else {
      subjectString = 'school corporations';
    }

    return (
      <p>
        Ranking {subjectString}
      </p>
    );
  }

  render() {
    const resultsCount = this.props.results.length;
    if (resultsCount === 0) {
      const subjectsNotFound = this.contextIsSchool() ?
        'schools' :
        'school corporations';

      return (
        <section>
          <h1>
            Ranking Results
          </h1>
          <h3>
            No Results
          </h3>
          <p>
            No {subjectsNotFound} were found with data matching your selected criteria.
          </p>
        </section>
      );
    }

    const rankRows = [];

    for (let i = 0; i < resultsCount; i++) {
      const rank = this.props.results[i];
      rankRows.push(
        <tr key={rank.rank + '-0'}>
          <th rowSpan={rank.subjects.length} className="rank-number">
            {rank.rank}
          </th>
          {this.getResultCell(rank.subjects[0])}
        </tr>
      );
      for (let k = 1; k < rank.subjects.length; k++) {
        rankRows.push(
          <tr key={rank.rank + '-' + k}>
            {this.getResultCell(rank.subjects[k])}
          </tr>
        );
      }
    }

    const countHeader = resultsCount + ' Result' + (resultsCount > 1 ? 's' : '');
    const subjectHeader = this.contextIsSchool() ?
      'School' :
      'School Corporation';

    return (
      <section>
        <h1>
          Ranking Results
        </h1>
        {this.getInputSummary()}
        <h3>
          {countHeader}
        </h3>
        <table className="table ranking-results">
          <thead>
            <tr>
              <th>
                Rank
              </th>
              <th>
                <div className="row">
                  <div className="col-lg-6">
                    {subjectHeader}
                  </div>
                  <div className="col-lg-6 d-none d-lg-block">
                    Statistics
                    <Button color="link" size="sm" onClick={this.toggleShowAllStatistics}>
                      {this.state.showAllStatistics ? 'Hide' : 'Show'} All
                    </Button>
                  </div>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            {rankRows}
          </tbody>
        </table>
      </section>
    );
  }
}

RankingResults.propTypes = {
  results: PropTypes.array.isRequired,
  submittedData: PropTypes.object.isRequired,
};

export {RankingResults};
