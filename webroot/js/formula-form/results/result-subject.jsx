import React from 'react';
import PropTypes from 'prop-types';
import {library, dom} from '@fortawesome/fontawesome-svg-core';
import {fas} from '@fortawesome/free-solid-svg-icons';
import {Button} from 'reactstrap';

// FontAwesome
library.add(fas);
dom.watch();

class ResultSubject extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      showStatistics: props.showStatistics,
    };
    this.toggleShowStatistics = this.toggleShowStatistics.bind(this);
  }

  componentDidUpdate(prevProps) {
    if (prevProps.showStatistics !== this.props.showStatistics) {
      this.setState({showStatistics: this.props.showStatistics});
    }
  }

  getDataCompletenessWarning() {
    const dataCompleteness = this.props.dataCompleteness;
    if (dataCompleteness !== 'partial') {
      return;
    }

    return (
      <p className="data-completeness-warning alert alert-warning">
        Some data unavailable
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

    const rows = [];

    for (let i = 0; i < this.props.criteria.length; i++) {
      const criterion = this.props.criteria[i];
      const metricId = criterion.metric.metricId;
      const metricName = criterion.metric.name;
      const statistic = ResultSubject.getStatistic(statistics, metricId);
      const key = this.props.subjectData.id + '-stat-' + i;

      rows.push(
        <tr key={key}>
          <th>
            <span className="metric-name">
              {metricName}
            </span>
          </th>
          {statistic !== false &&
            <td>
              {statistic.value}
              <span className="year">
                {statistic.year}
              </span>
            </td>
          }
          {statistic === false &&
            <td className="missing-data">
              Unknown
            </td>
          }
        </tr>
      );
    }

    return (
      <table className="statistics">
        <tbody>
          {rows}
        </tbody>
      </table>
    );
  }

  getRankedStats(statistics) {
    if (this.props.dataCompleteness === 'empty') {
      return null;
    }

    const rows = [];

    for (let i = 0; i < this.props.criteria.length; i++) {
      const criterion = this.props.criteria[i];
      const metricId = criterion.metric.metricId;
      const metricName = criterion.metric.name;
      const statistic = ResultSubject.getStatistic(statistics, metricId);
      if (!statistic.rank) {
        continue;
      }
      const key = this.props.subjectData.id + '-stat-rank-' + i;
      let displayedRank = null;
      switch (statistic.rank) {
        case 1:
          displayedRank = <span>Highest score for {metricName}</span>;
          break;
        case 2:
          displayedRank = <span>2<sup>nd</sup> highest score for {metricName}</span>;
          break;
        case 3:
          displayedRank = <span>3<sup>rd</sup> highest score for {metricName}</span>;
          break;
        default:
          continue;
      }

      rows.push(
        <li key={key} className={'stat-ranked stat-ranked-' + statistic.rank}>
          <i className="fas fa-trophy"></i>
          {displayedRank}
        </li>
      );
    }

    if (rows.length === 0) {
      return null;
    }

    return (
      <ul className="stats-ranked">
        {rows}
      </ul>
    );
  }

  static getStatistic(statistics, metricId) {
    for (let i = 0; i < statistics.length; i++) {
      if (statistics[i].metric_id === metricId) {
        return statistics[i];
      }
    }

    return false;
  }

  capitalize(string) {
    if (typeof string !== 'string') {
      return string;
    }
    return string.charAt(0).toUpperCase() + string.slice(1);
  }

  /**
   * Takes the current school's array of grade levels and returns a string describing them
   *
   * @return {string}
   */
  getDisplayedGradeLevels() {
    const gradeLevels = this.props.subjectData.grades;
    if (!Array.isArray(gradeLevels) || gradeLevels.length === 0) {
      return '';
    }

    // Collect numbered grades (e.g. Grade 10) and non-numbered grades (e.g. Kindergarten) separately
    const namedGradeLevels = [];
    const numberedGradeLevels = [];
    for (let n = 0; n < gradeLevels.length; n++) {
      const name = gradeLevels[n].name.toLowerCase();
      if (name.search('grade') === -1) {
        namedGradeLevels.push(name);
        continue;
      }
      const gradeNumber = name.replace('grade ', '');
      numberedGradeLevels.push(gradeNumber);
    }

    // Shorten a group of grades (9, 10, 11, 12) into a range (9-12), assuming that no school skips any grades
    const retval = namedGradeLevels;
    if (numberedGradeLevels.length === 1) {
      retval.push('grade ' + numberedGradeLevels[0]);
    } else if (numberedGradeLevels.length > 1) {
      retval.push('grades ' + Math.min(...numberedGradeLevels) + '-' + Math.max(...numberedGradeLevels));
    }

    // Add serial commas and "and"
    if (retval.length > 1) {
      const lastIndex = retval.length - 1;
      retval[lastIndex] = 'and ' + retval[lastIndex];
    }
    const delimiter = retval.length > 2 ? ', ' : ' ';

    return ' teaching ' + retval.join(delimiter);
  }

  toggleShowStatistics() {
    this.setState({showStatistics: !this.state.showStatistics});
  }

  render() {
    return (
      <td key={this.props.subjectData.id}>
        <div className="row">
          <div className="col-lg-6 school-info">
            <h3 className="school-name">
              {this.props.subjectData.name}
            </h3>
            {this.props.context === 'school' &&
              <p>
                {this.capitalize(this.props.subjectData.school_type.name)} school
                {this.getDisplayedGradeLevels()}
              </p>
            }
            {this.props.context === 'school' && this.props.subjectData.address &&
              <p>
                {ResultSubject.nl2br(this.props.subjectData.address)}
              </p>
            }
            <p>
              {this.props.subjectData.phone &&
                <span>
                  {this.props.subjectData.phone} <br />
                </span>
              }
              {this.props.subjectData.url &&
                <a href={this.props.subjectData.url} target="_blank"
                   rel="noopener noreferrer">
                  Visit website
                </a>
              }
            </p>
          </div>
          <div className="col-lg-6 school-stats">
            <h4 className="d-lg-none">
              Statistics
            </h4>
            {this.getRankedStats(this.props.statistics)}
            <Button color="secondary" size="sm" onClick={this.toggleShowStatistics}>
              {this.state.showStatistics ? 'Hide' : 'Show'} Statistics
            </Button>
            {this.state.showStatistics && this.getDataCompletenessWarning()}
            {this.state.showStatistics && this.getStatValues(this.props.statistics)}
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
  showStatistics: PropTypes.bool.isRequired,
  statistics: PropTypes.array.isRequired,
  subjectData: PropTypes.object.isRequired,
};

export {ResultSubject};
