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
    let rankRows = [];

    for (let i = 0; i < this.props.results.length; i++) {
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

    return (
      <table className="table ranking-results">
        <tbody>
          {rankRows}
        </tbody>
      </table>
    );
  }
}

RankingResults.propTypes = {
  criteria: PropTypes.array.isRequired,
  results: PropTypes.array.isRequired,
};

export {RankingResults};
