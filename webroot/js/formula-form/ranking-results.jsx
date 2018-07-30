import React from 'react';
import PropTypes from 'prop-types';
import {SchoolResult} from './school-result.jsx';
import {DistrictResult} from './district-result.jsx';

class RankingResults extends React.Component {
  constructor(props) {
    super(props);
  }

  static getResultCell(subject) {
    if (subject.hasOwnProperty('school')) {
      return <SchoolResult data={subject.school}
                           dataCompleteness={subject.data_completeness} />;
    }
    if (subject.hasOwnProperty('school_district')) {
      return <DistrictResult data={subject.school_district}
                             dataCompleteness={subject.data_completeness} />;
    }

    console.log(
        'Error: Neither school nor school district found in result'
    );
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
            {RankingResults.getResultCell(rank.subjects[0])}
          </tr>
      );
      for (let k = 1; k < rank.subjects.length; k++) {
        rankRows.push(
            <tr key={rank.rank + '-' + k}>
              {RankingResults.getResultCell(rank.subjects[k])}
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
  results: PropTypes.array.isRequired,
};

export {RankingResults};
