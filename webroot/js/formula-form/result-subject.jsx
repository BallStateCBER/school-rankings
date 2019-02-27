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
    let className = '';
    if (dataCompleteness === 'partial') {
      msg = 'Some data unavailable';
      className = 'alert-warning';
    } else if (dataCompleteness === 'empty') {
      msg = 'No data available';
      className = 'alert-danger';
    }
    className = 'data-completeness-warning alert ' + className;

    return (
      <p className={className}>
        {msg}
      </p>
    );
  }

  static nl2br(str) {
    return str.split('\n').map((item, key) => {
      return <span key={key}>{item}<br/></span>;
    });
  }

  getStatValues(statistics) {
    if (this.props.dataCompleteness === 'empty') {
      return null;
    }

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
        <div className="row">
          <div className="col-lg-6">
            <h3 className="school-name">
              {this.props.subjectData.name}
            </h3>
            {this.props.context === 'school' &&
              <span>
                {ResultSubject.nl2br(this.props.subjectData.address)}<br />
              </span>
            }
            {this.props.subjectData.phone} <br />
            <a href={this.props.subjectData.url} target="_blank"
               rel="noopener noreferrer">
              Visit website
            </a>
          </div>
          <div className="col-lg-6">
            {this.getDataCompletenessWarning()}
            {this.getStatValues(this.props.statistics)}
          </div>
        </div>
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
