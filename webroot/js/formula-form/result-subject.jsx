import React from 'react';
import PropTypes from 'prop-types';

class ResultSubject extends React.Component {
  constructor(props) {
    super(props);
  }

  getDataCompletenessWarning() {
    const dataCompleteness = this.props.dataCompleteness;
    if (dataCompleteness === 'full') {
      return;
    }

    let msg = '';
    if (dataCompleteness === 'partial') {
      msg = 'Some data unavailable';
    } else if (dataCompleteness === 'empty') {
      msg = 'No data available';
    }

    return (
        <span className="data-completeness-warning">
        {msg}
      </span>
    );
  }

  static nl2br(str) {
    return str.split('\n').map((item, key) => {
      return <span key={key}>{item}<br/></span>;
    });
  }

  getStatValues(statistics) {
    let rows = [];

    for (let i = 0; i < this.props.criteria.length; i++) {
      const criterion = this.props.criteria[i];
      const metricId = criterion.metric.metricId;
      const metricName = criterion.metric.name;
      const statisticValue = ResultSubject.getStatValue(statistics, metricId);
      const statisticYear = ResultSubject.getStatYear(statistics, metricId);
      const key = this.props.subjectData.id + '-stat-' + i;

      rows.push(
        <tr key={key}>
          <th>
            <span className="metric-name">
              {metricName}
            </span>
          </th>
          {statisticValue !== false &&
            <td>
              {statisticValue}
              <span className="year">
                {statisticYear}
              </span>
            </td>
          }
          {statisticValue === false &&
            <td className="missing-data">
              Unknown
            </td>
          }
        </tr>
      );
    }

    return (
      <table>
        <tbody>
          {rows}
        </tbody>
      </table>
    );
  }

  static getStatValue(statistics, metricId) {
    for (let i = 0; i < statistics.length; i++) {
      if (statistics[i].metric_id === metricId) {
        return statistics[i].value;
      }
    }

    return false;
  }

  static getStatYear(statistics, metricId) {
    for (let i = 0; i < statistics.length; i++) {
      if (statistics[i].metric_id === metricId) {
        return statistics[i].year;
      }
    }

    return false;
  }

  render() {
    return (
        <td key={this.props.subjectData.id}>
          {this.props.subjectData.name} <br />
          {this.props.context === 'school' &&
            <span>
              {ResultSubject.nl2br(this.props.subjectData.address)}<br />
            </span>
          }
          {this.props.subjectData.phone} <br />
          <a href={this.props.subjectData.url} target="_blank"
             rel="noopener noreferrer">
            {this.props.subjectData.url}
          </a><br />
          {this.getDataCompletenessWarning()}
          <p>
            Statistics:
          </p>
          {this.getStatValues(this.props.statistics)}
        </td>
    );
  }
}

ResultSubject.propTypes = {
  context: PropTypes.string.isRequired,
  criteria: PropTypes.array.isRequired,
  dataCompleteness: PropTypes.string.isRequired,
  statistics: PropTypes.array.isRequired,
  subjectData: PropTypes.object.isRequired,
};

export {ResultSubject};
