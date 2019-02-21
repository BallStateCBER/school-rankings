import React from 'react';
import PropTypes from 'prop-types';
import {ResultSubject} from './result-subject.jsx';

class RankingResults extends React.Component {
  constructor(props) {
    super(props);
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
                          criteria={this.props.criteria}
                          context={context} />;
  }

  render() {
    const resultsCount = this.props.results.length;
    if (resultsCount === 0) {
      const subjects = this.props.context === 'school'
        ? 'schools'
        : 'school corporations';

      return <section>
          <h3>
            No Results
          </h3>
          <p>
            No {subjects} were found matching your selected criteria.
          </p>
        </section>;
    }

    let rankRows = [];

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

    const header = resultsCount + ' Result' + (resultsCount > 1 ? 's' : '');

    return (
      <section>
        <h3>
          {header}
        </h3>
        <table className="table ranking-results">
          <tbody>
            {rankRows}
          </tbody>
        </table>
      </section>
    );
  }
}

RankingResults.propTypes = {
  criteria: PropTypes.array.isRequired,
  results: PropTypes.array.isRequired,
  context: PropTypes.string.isRequired,
};

export {RankingResults};
