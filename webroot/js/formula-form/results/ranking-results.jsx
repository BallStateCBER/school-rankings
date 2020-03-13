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

  render() {
    const resultsCount = this.props.results.length;
    if (resultsCount === 0) {
      const subjectsNotFound = this.contextIsSchool() ?
        'schools' :
        'school corporations';

      return (
        <div>
          <h3>
            No Results
          </h3>
          <p>
            No {subjectsNotFound} were found with data matching your selected criteria.
          </p>
        </div>
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
      <div>
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
      </div>
    );
  }
}

RankingResults.propTypes = {
  results: PropTypes.array.isRequired,
  submittedData: PropTypes.object.isRequired,
};

export {RankingResults};
